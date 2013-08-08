<?php

namespace SMW\Test;

use SMW\PropertiesQueryPage;
use SMW\MessageFormatter;
use SMW\ArrayAccessor;

use SMWDataItem;

/**
 * Tests for the PropertiesQueryPage class
 *
 * @file
 *
 * @license GNU GPL v2+
 * @since   1.9
 *
 * @author mwjames
 */

/**
 * @covers \SMW\PropertiesQueryPage
 *
 * @ingroup Test
 *
 * @group SMW
 * @group SMWExtension
 */
class PropertiesQueryPageTest extends SemanticMediaWikiTestCase {

	/**
	 * Returns the name of the class to be tested
	 *
	 * @return string|false
	 */
	public function getClass() {
		return '\SMW\PropertiesQueryPage';
	}

	/**
	 * Helper method that returns a DIWikiPage object
	 *
	 * @since 1.9
	 *
	 * @return DIWikiPage
	 */
	private function getMockDIWikiPage( $exists = true ) {

		$text  = $this->getRandomString();

		$title = $this->newMockObject( array(
			'exists'  => $exists,
			'getText' => $text,
			'getNamespace'    => NS_MAIN,
			'getPrefixedText' => $text
		) )->getMockTitle();

		$diWikiPage = $this->newMockObject( array(
			'getTitle'  => $title,
		) )->getMockDIWikiPage();

		return $diWikiPage;
	}

	/**
	 * Helper method that returns a Collector object
	 *
	 * @since 1.9
	 *
	 * @param $result
	 *
	 * @return Collector
	 */
	private function getMockCollector( $result = null ) {

		$collector = $this->getMockBuilder( '\SMW\Store\Collector' )
			->setMethods( array( 'cacheAccessor', 'doCollect', 'getResults' ) )
			->getMock();

		$collector->expects( $this->any() )
			->method( 'getResults' )
			->will( $this->returnValue( $result ) );

		return $collector;
	}

	/**
	 * Helper method that returns a PropertiesQueryPage object
	 *
	 * @since 1.9
	 *
	 * @param $result
	 *
	 * @return PropertiesQueryPage
	 */
	private function getInstance( $result = null, $values = array(), $settings = array() ) {

		$store = $this->newMockObject( array(
			'getPropertyValues'    => $values,
			'getPropertiesSpecial' => $this->getMockCollector( $result )
		) )->getMockStore();

		if ( $settings === array() ) {
			$settings = array(
				'smwgPDefaultType' => '_wpg',
				'smwgPropertyLowUsageThreshold' => 5,
				'smwgPropertyZeroCountDisplay' => true
			);
		}

		$instance = new PropertiesQueryPage( $store, $this->getSettings( $settings ) );
		$instance->setContext( $this->newContext() );

		return $instance;
	}

	/**
	 * @test PropertiesQueryPage::__construct
	 *
	 * @since 1.9
	 */
	public function testConstructor() {
		$this->assertInstanceOf( $this->getClass(), $this->getInstance() );
	}

	/**
	 * @test PropertiesQueryPage::formatResult
	 *
	 * @since 1.9
	 */
	public function testFormatResultDIError() {

		$skin = $this->getMock( 'Skin' );

		$instance = $this->getInstance();
		$error    = $this->getRandomString();

		$result   = $instance->formatResult(
			$skin,
			array( $this->newMockobject( array( 'getErrors' => $error ) )->getMockDIError(), null )
		);

		$this->assertInternalType( 'string', $result );
		$this->assertContains( $error, $result );

	}

	/**
	 * @test PropertiesQueryPage::formatResult
	 *
	 * @since 1.9
	 */
	public function testInvalidResultException() {

		$this->setExpectedException( '\SMW\InvalidResultException' );

		$instance = $this->getInstance();
		$skin = $this->getMock( 'Skin' );

		$this->assertInternalType( 'string', $instance->formatResult( $skin, null ) );

	}

	/**
	 * @test PropertiesQueryPage::formatResult
	 * @dataProvider getUserDefinedDataProvider
	 *
	 * @note Title, wikiPage, and property label are randomized therefore
	 * the expected comparison value is determined after the property object
	 * has been mocked
	 *
	 * @since 1.9
	 */
	public function testFormatPropertyItemUserDefined( $isUserDefined ) {

		$skin = $this->getMock( 'Skin' );

		// Title exists
		$count    = rand();
		$instance = $this->getInstance();
		$property = $this->newMockObject( array(
			'isUserDefined' => $isUserDefined,
			'getDiWikiPage' => $this->getMockDIWikiPage( true ),
			'getLabel'      => $this->getRandomString(),
		) )->getMockDIProperty();

		$expected = $property->getDiWikiPage()->getTitle()->getText();
		$result   = $instance->formatResult( $skin, array( $property, $count ) );

		$this->assertInternalType( 'string', $result );
		$this->assertContains( $expected, $result );

		// Title does not exists
		$count    = rand();
		$instance = $this->getInstance();

		$property = $this->newMockObject( array(
			'isUserDefined' => $isUserDefined,
			'getDiWikiPage' => $this->getMockDIWikiPage( false ),
			'getLabel'      => $this->getRandomString(),
		) )->getMockDIProperty();

		$expected = $property->getDiWikiPage()->getTitle()->getText();
		$result   = $instance->formatResult( $skin, array( $property, $count ) );

		$this->assertInternalType( 'string', $result );
		$this->assertContains( $expected, $result );

		// Multiple entries
		$count    = rand();
		$multiple = array( $this->getMockDIWikiPage(), $this->getMockDIWikiPage() );

		$property = $this->newMockObject( array(
			'isUserDefined' => $isUserDefined,
			'getDiWikiPage' => $this->getMockDIWikiPage( true ),
			'getLabel'      => $this->getRandomString(),
		) )->getMockDIProperty();

		$expected = $property->getDiWikiPage()->getTitle()->getText();
		$instance = $this->getInstance( null, $multiple );

		$result   = $instance->formatResult( $skin, array( $property, $count ) );

		$this->assertInternalType( 'string', $result );
		$this->assertContains( $expected, $result );

	}

	/**
	 * @test PropertiesQueryPage::formatResult
	 *
	 * @since 1.9
	 */
	public function testFormatPropertyItemZeroDisplay() {

		$skin = $this->getMock( 'Skin' );

		$count    = 0;
		$instance = $this->getInstance( null, array(), array(
			'smwgPropertyZeroCountDisplay' => false
		) );

		$property = $this->newMockObject( array(
			'isUserDefined' => true,
			'getDiWikiPage' => $this->getMockDIWikiPage( true ),
			'getLabel'      => $this->getRandomString(),
		) )->getMockDIProperty();

		$result = $instance->formatResult( $skin, array( $property, $count ) );

		$this->assertInternalType( 'string', $result );
		$this->assertEmpty( $result );
	}

	/**
	 * @test PropertiesQueryPage::formatResult
	 *
	 * @since 1.9
	 */
	public function testFormatPropertyItemTitleNull() {

		$skin = $this->getMock( 'Skin' );

		$count    = rand();
		$instance = $this->getInstance();

		$property = $this->newMockObject( array(
			'isUserDefined' => true,
			'getLabel'      => $this->getRandomString(),
		) )->getMockDIProperty();

		$expected = $property->getLabel();
		$result   = $instance->formatResult( $skin, array( $property, $count ) );

		$this->assertInternalType( 'string', $result );
		$this->assertContains( $expected, $result );
	}

	/**
	 * @test PropertiesQueryPage::formatResult
	 *
	 * @since 1.9
	 */
	public function testFormatPropertyItemLowUsageThreshold() {

		$skin = $this->getMock( 'Skin' );

		$count    = rand();
		$instance = $this->getInstance( null, array(), array(
			'smwgPropertyLowUsageThreshold' => $count + 1,
			'smwgPDefaultType' => '_wpg'
		) );

		$property = $this->newMockObject( array(
			'isUserDefined' => true,
			'getDiWikiPage' => $this->getMockDIWikiPage( true ),
			'getLabel'      => $this->getRandomString(),
		) )->getMockDIProperty();

		$expected = $property->getLabel();
		$result   = $instance->formatResult( $skin, array( $property, $count ) );

		$this->assertInternalType( 'string', $result );
		$this->assertContains( $expected, $result );
	}

	/**
	 * isUserDefined switcher
	 *
	 * @return array
	 */
	public function getUserDefinedDataProvider() {
		return array( array( true ), array( false ) );
	}

	/**
	 * @test PropertiesQueryPage::getResults
	 *
	 * @since 1.9
	 */
	public function testGetResults() {

		$expected = 'Lala';

		$instance = $this->getInstance( $expected );
		$this->assertEquals( $expected, $instance->getResults( null ) );

	}

	/**
	 * @test PropertiesQueryPage::getPageHeader
	 *
	 * @since 1.9
	 */
	public function testGetPageHeader() {

		$propertySearch = $this->getRandomString();

		$context = $this->newContext( array( 'property' => $propertySearch ) );
		$context->setTitle( $this->newTitle() );

		$instance = $this->getInstance();
		$instance->setContext( $context );
		$instance->getResults( null );

		$matcher = array(
			'tag' => 'p',
			'attributes' => array( 'class' => 'smw-sp-properties-docu' ),
			'tag' => 'input',
			'attributes' => array( 'name' => 'property', 'value' => $propertySearch ),
			'tag' => 'input',
			'attributes' => array( 'type' => 'submit' )
		);

		$this->assertTag( $matcher, $instance->getPageHeader() );

	}

}
