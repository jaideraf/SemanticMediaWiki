<?php

namespace SMW\SQLStore;

/**
 * @license GNU GPL v2+
 * @since 2.4
 *
 * @author mwjames
 */
class PropertyTableOutdatedReferenceDisposer {

	/**
	 * @var SQLStore
	 */
	private $store = null;

	/**
	 * @since 2.4
	 *
	 * @param SQLStore $store
	 */
	public function __construct( SQLStore $store ) {
		$this->store = $store;
	}

	/**
	 * After a property changed its type (_wpg -> _txt), object values in the
	 * ID table are not removed at the time of the conversion process. This method
	 * (as a post-processing during the ByIdDataRebuildDispatcher) checks
	 * whether an ID is without reference and removes it if necessary.
	 *
	 * @note This method does not check for an ID being object or subject value
	 * and has to be done prior calling this routine.
	 *
	 * @since 2.4
	 *
	 * @param integer $id
	 */
	public function attemptToRemoveOutdatedEntryFromIDTable( $id ) {

		$db = $this->store->getConnection( 'mw.db' );
		$canRemoveEntry = true;

		foreach ( $this->store->getPropertyTables() as $proptable ) {
			if ( $this->hasReferenceByTable( $proptable, $id ) ) {
				$canRemoveEntry = false;
				break;
			}
		}

		if ( $canRemoveEntry ) {
			$db->delete( SQLStore::ID_TABLE, array( 'smw_id' => $id ), __METHOD__ );
		}
	}

	/**
	 * @since 2.4
	 *
	 * @param integer $id
	 */
	public function removeAnyReferenceFromPropertyTablesFor( $id ) {

		$db = $this->store->getConnection( 'mw.db' );

		foreach ( $this->store->getPropertyTables() as $proptable ) {
			if ( $proptable->usesIdSubject() ) {
				$db->delete(
					$proptable->getName(),
					array( 's_id' => $id ),
					__METHOD__
				);
			}

			$fields = $proptable->getFields( $this->store );

			// Match tables (including ftp_redi) that contain an object reference
			if ( isset( $fields['o_id'] ) ) {
				$db->delete(
					$proptable->getName(),
					array( 'o_id' => $id ),
					__METHOD__
				);
			}
		}

		$db->delete(
			SQLStore::ID_TABLE,
			array( 'smw_id' => $id ),
			__METHOD__
		);
	}

	private function hasReferenceByTable( $proptable, $id ) {

		$row = false;
		$db = $this->store->getConnection( 'mw.db' );

		if ( $proptable->usesIdSubject() ) {
			$row = $db->selectRow(
				$proptable->getName(),
				array( 's_id' ),
				array( 's_id' => $id ),
				__METHOD__
			);
		}

		if ( $row !== false ) {
			return true;
		}

		$fields = $proptable->getFields( $this->store );

		// Check whether an object reference exists or not
		if ( isset( $fields['o_id'] ) ) {
			$row = $db->selectRow(
				$proptable->getName(),
				array( 'o_id' ),
				array( 'o_id' => $id ),
				__METHOD__
			);
		}

		// If the property table is not a fixed table (== assigns a whole
		// table to a specific property with the p_id column being suppressed)
		// then check for the p_id field
		if ( $row === false && !$proptable->isFixedPropertyTable() ) {
			$row = $db->selectRow(
				$proptable->getName(),
				array( 'p_id' ),
				array( 'p_id' => $id ),
				__METHOD__
			);
		}

		// If the query table contains a reference then we keep the object (could
		// be a subject, property, or printrequest) where in case the query is
		// removed the object will also loose its reference
		if ( $row === false ) {
			$row = $db->selectRow(
				SQLStore::QUERY_LINKS_TABLE,
				array( 'o_id' ),
				array( 'o_id' => $id ),
				__METHOD__
			);
		}

		return $row !== false;
	}

}
