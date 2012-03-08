<?php
/**
 * Json 
 *
 * @file JsonSchema.php
 * @ingroup Extensions
 * @author Rob Lanphier
 * @copyright © 2011-2012 Rob Lanphier
 * @licence http://jsonwidget.org/LICENSE BSD 3-clause
 */

/*
 * Note, this is a standalone component.  Please don't mix MediaWiki-specific
 * code or library calls into this file.
 */

class JsonSchemaException extends Exception {
}

class JsonUtil {
	/*
	 * Converts the string into something safe for an HTML id.
	 * performs the easiest transformation to safe id, but is lossy
	 */
	public static function stringToId( $var ) {
		if ( is_int( $var ) ) {
			return (string)$var;
		}

		elseif ( is_string( $var ) ) {
			return preg_replace( '/[^a-z0-9\-_:\.]/i', '', $var );
		} else {
			throw new JsonSchemaException( 'Cannot convert var to id' . print_r( $var, true ) );
		}

	}

	/*
	 * Given a type (e.g. 'map', 'int', 'str'), return the default/empty
	 * value for that type.
	 */
	public static function getNewValueForType( $thistype ) {
		switch( $thistype ) {
			case 'map':
				$newvalue = array();
				break;
			case 'seq':
				$newvalue = array();
				break;
			case 'number':
				case 'int':
					$newvalue = 0;
					break;
				case 'str':
					$newvalue = "";
					break;
				case 'bool':
					$newvalue = false;
					break;
				default:
					$newvalue = null;
					break;
		}

		return $newvalue;
	}

	/*
	 * Return a JSON-schema type for arbitrary data $foo
	 */
	public static function getType ( $foo ) {
		if ( is_null( $foo ) ) {
			return null;
		}

		switch( gettype( $foo ) ) {
			case "array":
				$retval = "seq";
				foreach ( array_keys( $foo ) as $key ) {
					if ( !is_int( $key ) ) {
						$retval = "map";
					}
				}
				return $retval;
				break;
			case "integer":
			case "double":
				return "number";
				break;
			case "boolean":
				return "bool";
				break;
			case "string":
				return "str";
				break;
			default:
				return null;
				break;
		}

	}

	/*
	 * Generate a schema from a data example ($parent)
	 */
	public static function getSchemaArray( $parent ) {
		$schema = array();
		$schema['type'] = JsonUtil::getType( $parent );
		switch ( $schema['type'] ) {
			case 'map':
				$schema['mapping'] = array();
				foreach ( $parent as $name ) {
					$schema['mapping'][$name] = JsonUtil::getSchemaArray( $parent[$name] );
				}

				break;
			case 'seq':
				$schema['sequence'] = array();
				$schema['sequence'][0] = JsonUtil::getSchemaArray( $parent[0] );
				break;
		}

		return $schema;
	}

}


/*
 * Internal terminology:
 *   Node: "node" in the graph theory sense, but specifically, a node in the
 *    raw PHP data representation of the structure
 *   Ref: a node in the object tree.  Refs contain nodes and metadata about the
 *    nodes, as well as pointers to parent refs
 */

/*
 * Structure for representing a generic tree which each node is aware of its
 * context (can refer to its parent).  Used for schema refs.
 */

class TreeRef {
	public $node;
	public $parent;
	public $nodeindex;
	public $nodename;
	public function __construct( $node, $parent, $nodeindex, $nodename ) {
		$this->node = $node;
		$this->parent = $parent;
		$this->nodeindex = $nodeindex;
		$this->nodename = $nodename;
	}
}

/*
 * Structure for representing a data tree, where each node (ref) is aware of its
 * context and associated schema.
 */

class JsonTreeRef {
	public function __construct( $node, $parent = null, $nodeindex = null, $nodename = null, $schemaref = null ) {
		$this->node = $node;
		$this->parent = $parent;
		$this->nodeindex = $nodeindex;
		$this->nodename = $nodename;
		$this->schemaref = $schemaref;
		$this->fullindex = $this->getFullIndex();
		$this->datapath = array();
		if ( !is_null( $schemaref ) ) {
			$this->attachSchema();
		}
	}

	/*
	 * Associate the relevant node of the JSON schema to this node in the JSON
	 */
	public function attachSchema( $schema = null ) {
		if ( !is_null( $schema ) ) {
			$this->schemaindex = new JsonSchemaIndex( $schema );
			$this->nodename =
				isset( $schema['title'] ) ? $schema['title'] : "Root node";
			$this->schemaref = $this->schemaindex->newRef( $schema, null, null, $this->nodename );
		}
		elseif ( !is_null( $this->parent ) ) {
			$this->schemaindex = $this->parent->schemaindex;
		}
		if ( $this->schemaref->node['type'] == 'any' ) {
			if ( $this->getType() == 'map' ) {
				$this->schemaref->node['mapping'] =
					array( "extension" => array( "type" => "any" ) );
				$this->schemaref->node['user_key'] = "extension";
			}

			elseif ( $this->getType() == 'seq' ) {
				$this->schemaref->node['sequence'] =
					array( array( "type" => "any" ) );
				$this->schemaref->node['user_key'] = "extension";
			}

		}

	}

	/*
	 *  Return the title for this ref, typically defined in the schema as the
	 *  user-friendly string for this node.
	 */
	public function getTitle() {
		if ( isset( $this->nodename ) ) {
			return $this->nodename;
		} elseif ( isset( $this->node['title'] ) ) {
			return $this->node['title'];
		} else {
			return $this->nodeindex;
		}
	}

	/*
	 *  Is this node a "user key", as defined in http://jsonwidget.org/jsonschema/
	 */
	public function isUserKey() {
		return $this->userkeyflag;
	}

	/*
	 * Rename a user key.  Useful for interactive editing/modification, but not
	 * so helpful for static interpretation.
	 */
	public function renamePropname( $newindex ) {
		$oldindex = $this->nodeindex;
		$this->parent->node[$newindex] = $this->node;
		$this->nodeindex = $newindex;
		$this->nodename = $newindex;
		$this->fullindex = $this->getFullIndex();
		unset( $this->parent->node[$oldindex] );
	}

	/*
	 * Return the type of this node as specified in the schema.  If "any",
	 * infer it from the data.
	 */
	public function getType() {
		$nodetype = $this->schemaref->node['type'];

		if ( $nodetype == 'any' ) {
			if ( $this->node == null ) {
				return null;
			} else {
				return JsonUtil::getType( $this->node );
			}
		} else {
			return $nodetype;
		}

	}

	/*
	 * Return a unique identifier that may be used to find a node.  This
	 * is only as robust as stringToId is (i.e. not that robust), but is
	 * good enough for many cases.
	 */
	public function getFullIndex() {
		if ( is_null( $this->parent ) ) {
			return "json_root";
		} else {
			return $this->parent->getFullIndex() + "." + JsonUtil::stringToId( $this->nodeindex );
		}
	}

	/*
	 *  Get a path to the element in the array.  if $foo['a'][1] would load the
	 *  node, then the return value of this would be array('a',1)
	 */
	public function getDataPath() {
		if ( !is_object( $this->parent ) ) {
			return array();
		} else {
			$retval = $this->parent->getDataPath();
			$retval[] = $this->nodeindex;
			return $retval;
		}
	}

	/*
	 *  Return path in something that looks like an array path.  For example,
	 *  for this data: [{'0a':1,'0b':{'0ba':2,'0bb':3}},{'1a':4}]
	 *  the leaf node with a value of 4 would have a data path of '[1]["1a"]',
	 *  while the leaf node with a value of 2 would have a data path of
	 *  '[0]["0b"]["oba"]'
	 */
	public function getDataPathAsString() {
		$retval = "";
		foreach( $this->getDataPath() as $item ) {
			$retval .= '[' . json_encode( $item ) . ']';
		}
		return $retval;
	}

	/*
	 *  Return data path in user-friendly terms.  This will use the same
	 *  terminology as used in the user interface (1-indexed arrays)
	 */
	public function getDataPathTitles() {
		if ( !is_object( $this->parent ) ) {
			return $this->getTitle();
		} else {
			return $this->parent->getDataPathTitles() . ' -> ' 
				. $this->getTitle();
		}
	}

	/*
	 * Return the child ref for $this ref associated with a given $key
	 */
	public function getMappingChildRef( $key ) {
		if ( array_key_exists( 'user_key', $this->schemaref->node ) &&
			!array_key_exists( $key, $this->schemaref->node['mapping'] ) ) {
			$userkeyflag = true;
			$masterkey = $this->schemaref->node['user_key'];
			$schemadata = $this->schemaref->node['mapping'][$masterkey];
		}
		else {
			$userkeyflag = false;
			if( array_key_exists( $key, $this->schemaref->node['mapping'] ) ) {
				$schemadata = $this->schemaref->node['mapping'][$key];
			} else {
				throw new JsonSchemaException( 'Invalid key ' . $key .
					" in ". $this->getDataPathTitles() );
			}
		}
		$value = $this->node[$key];
		$nodename = isset( $schemadata['title'] ) ? $schemadata['title'] : $key;
		$schemai = $this->schemaindex->newRef( $schemadata, $this->schemaref, $key, $key );
		$jsoni = new JsonTreeRef( $value, $this, $key, $nodename, $schemai );
		return $jsoni;
	}

	/*
	 * Return the child ref for $this ref associated with a given index $i
	 */
	public function getSequenceChildRef( $i ) {
		$schemanode = $this->schemaref->node['sequence'][0];
		$itemname = isset( $schemanode['title'] ) ? $schemanode['title'] : "Item";
		$nodename = $itemname . " #" . ( (string)$i + 1 );
		$schemai = $this->schemaindex->newRef( $this->schemaref->node['sequence'][0], $this->schemaref, 0, $i );
		$jsoni = new JsonTreeRef( $this->node[$i], $this, $i, $nodename, $schemai );
		return $jsoni;
	}

	/*
	 * Validate the JSON node in this ref against the attached schema ref.
	 * Return true on success, and throw a JsonSchemaException on failure.
	 */
	public function validate() {
		$datatype = JsonUtil::getType( $this->node );
		$schematype = $this->getType();
		if ( $datatype == 'seq' && $schematype == 'map' ) {
			// PHP datatypes are kinda loose, so we'll fudge
			$datatype = 'map';
		}
		if ( $datatype == 'number' && $schematype == 'int' &&
			 $this->node == (int)$this->node) {
			// Alright, it'll work as an int
			$datatype = 'int';
		}
		if ( $datatype != $schematype ) {
			$datatype = is_null( $datatype ) ? "null" : $datatype;
			throw new JsonSchemaException( 'Invalid node: expecting ' . $schematype .
				', got ' . $datatype . '.  Path: ' .
				$this->getDataPathTitles() );
		}
		switch ( $schematype ) {
			case 'map':
				foreach ( $this->node as $key => $value ) {
					$jsoni = $this->getMappingChildRef( $key );
					$jsoni->validate();
				}
				break;
			case 'seq':
				for ( $i = 0; $i < count( $this->node ); $i++ ) {
					$jsoni = $this->getSequenceChildRef( $i );
					$jsoni->validate();
				}
				break;
		}
		return true;
	}
}


/*
 * The JsonSchemaIndex object holds all schema refs with an "id", and is used
 * to resolve an idref to a schema ref.  This also holds the root of the schema
 * tree.  This also serves as sort of a class factory for schema refs.
 */
class JsonSchemaIndex {
	public $root;
	public $idtable;
	/*
	 * The whole tree is indexed on instantiation of this class.
	 */
	public function __construct( $schema ) {
		$this->root = $schema;
		$this->idtable = array();

		if ( is_null( $this->root ) ) {
			return null;
		}

		$this->indexSubtree( $this->root );
	}

	/*
	 * Recursively find all of the ids in this schema, and store them in the
	 * index.
	 */
	public function indexSubtree( $schemanode ) {
		$nodetype = $schemanode['type'];
		switch( $nodetype ) {
			case 'map':
				foreach ( $schemanode['mapping'] as $key => $value ) {
					$this->indexSubtree( $value );
				}

				break;
			case 'seq':
				foreach ( $schemanode['sequence'] as $value ) {
					$this->indexSubtree( $value );
				}

				break;
		}
		if ( isset( $schemanode['id'] ) ) {
			$this->idtable[$schemanode['id']] = $schemanode;
		}
	}

	/*
	 *  Generate a new schema ref, or return an existing one from the index if
	 *  the node is an idref.
	 */
	public function newRef( $node, $parent, $nodeindex, $nodename ) {
		if ( $node['type'] == 'idref' ) {
			try {
				$node = $this->idtable[$node['idref']];
			}
			catch ( Exception $e ) {
				throw new JsonSchemaException( 'Bad idref: ' . $node['idref'] );
			}
		}

		return new TreeRef( $node, $parent, $nodeindex, $nodename );
	}
}
