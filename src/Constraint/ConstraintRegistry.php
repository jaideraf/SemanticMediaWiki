<?php

namespace SMW\Constraint;

use RuntimwException;
use SMW\ConstraintFactory;
use SMW\Constraint\Constraints\NullConstraint;
use SMW\Constraint\Constraints\CommonConstraint;
use SMW\Constraint\Constraints\UniqueValueConstraint;
use SMW\Constraint\Constraints\NonNegativeIntegerConstraint;


/**
 * @license GNU GPL v2+
 * @since 3.1
 *
 * @author mwjames
 */
class ConstraintRegistry {

	/**
	 * @var ConstraintFactory
	 */
	private $constraintFactory;

	/**
	 * @var []
	 */
	private $constraints = [];

	/**
	 * @var []
	 */
	private $instances = [];

	/**
	 * @var boolean
	 */
	private $hasViolation = false;

	/**
	 * @since 3.1
	 *
	 * @param ConstraintFactory $constraintFactory
	 */
	public function __construct( ConstraintFactory $constraintFactory ) {
		$this->constraintFactory = $constraintFactory;
	}

	/**
	 * @since 3.1
	 *
	 * @param string $key
	 * @param Constraint|string $constraint
	 */
	public function registerConstraint( $key, $constraint ) {

		if ( $this->constraints === [] ) {
			$this->initConstraints();
		}

		$this->constraints[$key] = $constraint;
	}

	/**
	 * @since 3.1
	 *
	 * @param string $key
	 *
	 * @return Constraint
	 */
	public function getConstraintByKey( $key ) {

		if ( $this->constraints === [] ) {
			$this->initConstraints();
		}

		if ( isset( $this->constraints[$key] ) ) {
			return $this->loadInstance( $this->constraints[$key] );
		}

		return $this->loadInstance( $this->constraints['null'] );
	}

	private function initConstraints() {

		$this->constraints = [
			'null' => NullConstraint::class,
			'allowed_namespaces' => CommonConstraint::class,
			'unique_value_constraint' => UniqueValueConstraint::class,
			'non_negative_integer' => NonNegativeIntegerConstraint::class
		];

		\Hooks::run( 'SMW::Constraint::initConstraints', [ $this ] );
	}

	private function loadInstance( $class ) {

		if ( is_callable( $class ) ) {
			return $class();
		} elseif ( $class instanceof Constraint ) {
			return $class;
		} elseif ( !isset( $this->instances[$class] ) ) {
			$this->instances[$class] = $this->constraintFactory->newConstraintByClass( $class );
		}

		return $this->instances[$class];
	}

}