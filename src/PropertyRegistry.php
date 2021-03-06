<?php

namespace SMW;

/**
 * @license GNU GPL v2+
 * @since 2.1
 *
 * @author mwjames
 * @author Markus Krötzsch
 */
class PropertyRegistry {

	/**
	 * @var PropertyRegistry
	 */
	private static $instance = null;

	/**
	 * @var PropertyLabelFinder
	 */
	private $propertyLabelFinder = null;

	/**
	 * Array for assigning types to predefined properties. Each
	 * property is associated with an array with the following
	 * elements:
	 *
	 * * ID of datatype to be used for this property
	 *
	 * * Boolean, stating if this property is shown in Factbox, Browse, and
	 *   similar interfaces; (note that this is only relevant if the
	 *   property can be displayed at all, i.e. has a translated label in
	 *   the wiki language; invisible properties are never shown).
	 *
	 * @var array
	 */
	private $propertyTypes = array();

	/**
	 * @var string[]
	 */
	private $datatypeLabels = array();

	/**
	 * Array with entries "property alias" => "property id"
	 * @var string[]
	 */
	private $propertyAliases = array();

	/**
	 * @since 2.1
	 *
	 * @return PropertyRegistry
	 */
	public static function getInstance() {

		if ( self::$instance === null ) {

			$propertyLabelFinder = new PropertyLabelFinder(
				ApplicationFactory::getInstance()->getStore(),
				$GLOBALS['smwgContLang']->getPropertyLabels()
			);

			self::$instance = new self(
				DataTypeRegistry::getInstance(),
				$propertyLabelFinder,
				$GLOBALS['smwgContLang']->getPropertyAliases()
			);

			self::$instance->registerPredefinedProperties( $GLOBALS['smwgUseCategoryHierarchy'] );
		}

		return self::$instance;
	}

	/**
	 * @since 2.1
	 *
	 * @param DataTypeRegistry $datatypeRegistry
	 * @param PropertyLabelFinder $propertyLabelFinder
	 * @param array $propertyAliases
	 */
	public function __construct( DataTypeRegistry $datatypeRegistry, PropertyLabelFinder $propertyLabelFinder, array $propertyAliases ) {

		$this->datatypeLabels = $datatypeRegistry->getKnownTypeLabels();
		$datatypeAliases = $datatypeRegistry->getKnownTypeAliases();
		$this->propertyLabelFinder = $propertyLabelFinder;

		foreach ( $this->datatypeLabels as $id => $label ) {
			$this->registerPropertyLabel( $id, $label );
		}

		foreach ( $datatypeAliases as $alias => $id ) {
			$this->registerPropertyAlias( $id, $alias );
		}

		foreach ( $propertyAliases as $alias => $id ) {
			$this->registerPropertyAlias( $id, $alias );
		}
	}

	/**
	 * @since 2.1
	 */
	public static function clear() {
		self::$instance = null;
	}

	/**
	 * @since 2.1
	 *
	 * @return array
	 */
	public function getKnownPropertyTypes() {
		return $this->propertyTypes;
	}

	/**
	 * @since 2.1
	 *
	 * @return array
	 */
	public function getKnownPropertyAliases() {
		return $this->propertyAliases;
	}

	/**
	 * A method for registering/overwriting predefined properties for SMW.
	 * It should be called from within the hook 'smwInitProperties' only.
	 * IDs should start with three underscores "___" to avoid current and
	 * future confusion with SMW built-ins.
	 *
	 * @param string $id
	 * @param string $typeId SMW type id
	 * @param string|bool $label user label or false (internal property)
	 * @param boolean $isVisibleToUser only used if label is given, see isShown()
	 * @param boolean $isAnnotableByUser
	 *
	 * @note See self::isShown() for information it
	 */
	public function registerProperty( $id, $typeId, $label = false, $isVisibleToUser = false, $isAnnotableByUser = true ) {

		$this->propertyTypes[$id] = array( $typeId, $isVisibleToUser, $isAnnotableByUser );

		if ( $label !== false ) {
			$this->registerPropertyLabel( $id, $label );
		}
	}

	/**
	 * Add a new alias label to an existing property ID. Note that every ID
	 * should have a primary label, either provided by SMW or registered
	 * with registerProperty().
	 *
	 * @param $id string id of a property
	 * @param $label string alias label for the property
	 *
	 * @note Always use registerProperty() for the first label. No property
	 * that has used "false" for a label on registration should have an
	 * alias.
	 */
	public function registerPropertyAlias( $id, $label ) {
		$this->propertyAliases[$label] = $id;
	}

	/**
	 * Get the translated user label for a given internal property ID.
	 * Returns empty string for properties without a translation (these are
	 * usually internal, generated by SMW but not shown to the user).
	 *
	 * @note An empty string is returned for incomplete translation (language
	 * bug) or deliberately invisible property
	 *
	 * @since 2.1
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	public function findPropertyLabelById( $id ) {
		return $this->propertyLabelFinder->findPropertyLabelById( $id );
	}

	/**
	 * @deprecated since 2.1 use findPropertyLabelById instead
	 */
	public function findPropertyLabel( $id ) {
		return $this->findPropertyLabelById( $id );
	}

	/**
	 * Get the type ID of a predefined property, or '' if the property
	 * is not predefined.
	 * The function is guaranteed to return a type ID for keys of
	 * properties where isUserDefined() returns false.
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	public function getPropertyTypeId( $id ) {

		if ( $this->isKnownPropertyId( $id ) ) {
			return $this->propertyTypes[$id][0];
		}

		return '';
	}

	/**
	 * @deprecated since 2.1 use getPropertyTypeId instead
	 */
	public function getPredefinedPropertyTypeId( $id ) {
		return $this->getPropertyTypeId( $id );
	}

	/**
	 * Find and return the ID for the pre-defined property of the given
	 * local label. If the label does not belong to a pre-defined property,
	 * return false.
	 *
	 * @param string $label normalized property label
	 * @param boolean $useAlias determining whether to check if the label is an alias
	 *
	 * @return mixed string property ID or false
	 */
	public function findPropertyIdByLabel( $label, $useAlias = true ) {

		$id = $this->propertyLabelFinder->searchPropertyIdByLabel( $label );

		if ( $id !== false ) {
			return $id;
		} elseif ( $useAlias && array_key_exists( $label, $this->propertyAliases ) ) {
			return $this->propertyAliases[$label];
		}

		return false;
	}

	/**
	 * @deprecated since 2.1 use findPropertyIdByLabel instead
	 */
	public function findPropertyId( $label, $useAlias = true ) {
		return $this->findPropertyIdByLabel( $label, $useAlias );
	}

	/**
	 * @since 2.1
	 *
	 * @param  string $id
	 *
	 * @return boolean
	 */
	public function isKnownPropertyId( $id ) {
		return isset( $this->propertyTypes[$id] ) || array_key_exists( $id, $this->propertyTypes );
	}

	/**
	 * @since 2.1
	 *
	 * @param  string $id
	 *
	 * @return boolean
	 */
	public function isVisibleToUser( $id ) {
		return $this->isKnownPropertyId( $id ) ? $this->propertyTypes[$id][1] : false;
	}

	/**
	 * @since 2.2
	 *
	 * @param  string $id
	 *
	 * @return boolean
	 */
	public function isUnrestrictedForAnnotationUse( $id ) {
		return $this->isKnownPropertyId( $id ) ? $this->propertyTypes[$id][2] : false;
	}

	/**
	 * @note All ids must start with underscores. The translation for each ID,
	 * if any, is defined in the language files. Properties without translation
	 * cannot be entered by or displayed to users, whatever their "show" value
	 * below.
	 */
	protected function registerPredefinedProperties( $useCategoryHierarchy ) {

		// array( Id, isVisibleToUser, isAnnotableByUser )

		$this->propertyTypes = array(
			'_TYPE'  => array( '__typ', true, true ), // "has type"
			'_URI'   => array( '__spu', true, true ), // "equivalent URI"
			'_INST'  => array( '__sin', false, true ), // instance of a category
			'_UNIT'  => array( '__sps', true, true ), // "displays unit"
			'_IMPO'  => array( '__imp', true, true ), // "imported from"
			'_CONV'  => array( '__sps', true, true ), // "corresponds to"
			'_SERV'  => array( '__sps', true, true ), // "provides service"
			'_PVAL'  => array( '__sps', true, true ), // "allows value"
			'_REDI'  => array( '__red', true, true ), // redirects to some page
			'_SUBP'  => array( '__sup', true, true ), // "subproperty of"
			'_SUBC'  => array( '__suc', !$useCategoryHierarchy, true ), // "subcategory of"
			'_CONC'  => array( '__con', false, true ), // associated concept
			'_MDAT'  => array( '_dat', false, false ), // "modification date"
			'_CDAT'  => array( '_dat', false, false ), // "creation date"
			'_NEWP'  => array( '_boo', false, false ), // "is a new page"
			'_LEDT'  => array( '_wpg', false, false ), // "last editor is"
			'_ERRC'  => array( '__sob', false, false ), // "has error"
			'_ERRT'  => array( '_txt', false, false ), // "has error text"
			'_ERRP'  => array( '_wpp', false, false ), // "has improper value for"
			'_LIST'  => array( '__pls', true, true ), // "has fields"
			'_SKEY'  => array( '__key', false, true ), // sort key of a page

			// FIXME SF related properties to be removed with 3.0
			'_SF_DF' => array( '__spf', true, true ), // Semantic Form's default form property
			'_SF_AF' => array( '__spf', true, true ),  // Semantic Form's alternate form property

			'_SOBJ'  => array( '__sob', true, false ), // "has subobject"
			'_ASK'   => array( '__sob', false, false ), // "has query"
			'_ASKST' => array( '_cod', true, true ), // "has query string"
			'_ASKFO' => array( '_txt', true, true ), // "has query format"
			'_ASKSI' => array( '_num', true, true ), // "has query size"
			'_ASKDE' => array( '_num', true, true ), // "has query depth"
			'_ASKDU' => array( '_num', true, true ), // "has query duration"
			'_MEDIA' => array( '_txt', true, false ), // "has media type"
			'_MIME'  => array( '_txt', true, false ), // "has mime type"
		);

		foreach ( $this->datatypeLabels as $id => $label ) {
			$this->propertyTypes[$id] = array( $id, true, true );
		}

		// @deprecated since 2.1
		\Hooks::run( 'smwInitProperties' );

		\Hooks::run( 'SMW::Property::initProperties', array( $this ) );
	}

	private function registerPropertyLabel( $id, $label ) {
		$this->propertyLabelFinder->registerPropertyLabel( $id, $label );
	}

}
