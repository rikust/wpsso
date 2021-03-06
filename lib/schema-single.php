<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2012-2019 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'WpssoSchemaCache' ) ) {
	require_once WPSSO_PLUGINDIR . 'lib/schema-cache.php';
}

if ( ! class_exists( 'WpssoSchemaSingle' ) ) {

	class WpssoSchemaSingle {

		protected $p;

		public function __construct( &$plugin ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
		}

		public static function add_event_data( &$json_data, array $mod, $event_id = false, $list_element = false ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->mark();
			}

			$ret =& self::have_local_data( $json_data, $mod, 'event', $event_id, $list_element );

			if ( false !== $ret ) {	// 0 or 1 if data was retrieved from the local static cache.
				return $ret;
			}

			$sharing_url = $wpsso->util->get_sharing_url( $mod );

			/**
			 * Maybe get options from Pro version integration modules.
			 */
			$event_opts = apply_filters( $wpsso->lca . '_get_event_options', false, $mod, $event_id );

			if ( ! empty( $event_opts ) ) {
				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log_arr( 'get_event_options filters returned', $event_opts );
				}
			}

			/**
			 * Add optional place ID.
			 */
			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->log( 'checking for custom event place id (null by default)' );
			}

			if ( ! isset( $event_opts[ 'event_location_id' ] ) ) {	// Make sure the array index exists.
				$event_opts[ 'event_location_id' ] = null;
			}

			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->log( 'applying the "get_event_location_id" filter to event place id ' . 
					( $event_opts[ 'event_location_id' ] === null ? 'null' : $event_opts[ 'event_location_id' ] ) );
			}

			$event_opts[ 'event_location_id' ] = apply_filters( $wpsso->lca . '_get_event_location_id', $event_opts[ 'event_location_id' ], $mod, $event_id );

			/**
			 * Add ISO date options.
			 */
			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->log( 'checking for custom event start/end date and time' );
			}

			WpssoSchema::add_mod_opts_date_iso( $mod, $event_opts, array( 
				'event_start_date'        => 'schema_event_start',        // Prefix for date, time, timezone, iso.
				'event_end_date'          => 'schema_event_end',          // Prefix for date, time, timezone, iso.
				'event_offers_start_date' => 'schema_event_offers_start', // Prefix for date, time, timezone, iso.
				'event_offers_end_date'   => 'schema_event_offers_end',   // Prefix for date, time, timezone, iso.
			) );

			/**
			 * Add event offers.
			 */
			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->log( 'checking for custom event offers' );
			}

			$have_event_offers = false;
			$event_offers_max  = SucomUtil::get_const( 'WPSSO_SCHEMA_EVENT_OFFERS_MAX', 10 );

			foreach ( range( 0, $event_offers_max - 1, 1 ) as $key_num ) {

				$offer_opts = apply_filters( $wpsso->lca . '_get_event_offer_options', false, $mod, $event_id, $key_num );

				if ( ! empty( $offer_opts ) ) {
					if ( $wpsso->debug->enabled ) {
						$wpsso->debug->log_arr( 'get_event_offer_options filters returned', $offer_opts );
					}
				}

				if ( ! is_array( $offer_opts ) ) {

					$offer_opts = array();

					foreach ( array( 
						'offer_name'           => 'schema_event_offer_name',
						'offer_url'            => 'schema_event_offer_url',
						'offer_price'          => 'schema_event_offer_price',
						'offer_price_currency' => 'schema_event_offer_currency',
						'offer_availability'   => 'schema_event_offer_avail',
					) as $opt_key => $md_pre ) {

						$offer_opts[ $opt_key ] = $mod[ 'obj' ]->get_options( $mod[ 'id' ], $md_pre . '_' . $key_num );
					}
				}

				/**
				 * Must have at least an offer name and price.
				 */
				if ( isset( $offer_opts[ 'offer_name' ] ) && isset( $offer_opts[ 'offer_price' ] ) ) {

					if ( ! isset( $event_opts[ 'offer_url' ] ) ) {

						if ( $wpsso->debug->enabled ) {
							$wpsso->debug->log( 'setting offer_url to ' . $sharing_url );
						}

						$offer_opts[ 'offer_url' ] = $sharing_url;
					}

					if ( ! isset( $offer_opts[ 'offer_valid_from_date' ] ) ) {

						if ( ! empty( $event_opts[ 'event_offers_start_date_iso' ] ) ) {

							if ( $wpsso->debug->enabled ) {
								$wpsso->debug->log( 'setting offer_valid_from_date to ' . $event_opts[ 'event_offers_start_date_iso' ] );
							}

							$offer_opts[ 'offer_valid_from_date' ] = $event_opts[ 'event_offers_start_date_iso' ];

						} elseif ( $wpsso->debug->enabled ) {
							$wpsso->debug->log( 'event option event_offers_start_date_iso is empty' );
						}
					}

					if ( ! isset( $offer_opts[ 'offer_valid_to_date' ] ) ) {

						if ( ! empty( $event_opts[ 'event_offers_end_date_iso' ] ) ) {

							if ( $wpsso->debug->enabled ) {
								$wpsso->debug->log( 'setting offer_valid_to_date to ' . $event_opts[ 'event_offers_end_date_iso' ] );
							}

							$offer_opts[ 'offer_valid_to_date' ] = $event_opts[ 'event_offers_end_date_iso' ];

						} elseif ( $wpsso->debug->enabled ) {
							$wpsso->debug->log( 'event option event_offers_end_date_iso is empty' );
						}
					}

					if ( false === $have_event_offers ) {

						$have_event_offers = true;

						if ( $wpsso->debug->enabled ) {
							$wpsso->debug->log( 'custom event offer found - creating new offers array' );
						}

						$event_opts[ 'event_offers' ] = array();	// Clear offers returned by filter.
					}

					$event_opts[ 'event_offers' ][] = $offer_opts;
				}
			}

			if ( empty( $event_opts ) ) {	// $event_opts could be false or empty array.

				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log( 'exiting early: empty event options' );
				}

				return 0;
			}

			/**
			 * If not adding a list element, inherit the existing schema type url (if one exists).
			 */
			list( $event_type_id, $event_type_url ) = self::get_type_id_url( $json_data, $event_opts, 'event_type', 'event', $list_element );

			$ret = WpssoSchema::get_schema_type_context( $event_type_url );

			/**
			 * Event organizer person.
			 *
			 * Use is_valid_option_id() to check that the id value is not true, false, null, or 'none'.
			 */
			if ( isset( $event_opts[ 'event_organizer_person_id' ] ) && SucomUtil::is_valid_option_id( $event_opts[ 'event_organizer_person_id' ] ) ) {
				if ( ! self::add_person_data( $ret[ 'organizer' ], $mod, $event_opts[ 'event_organizer_person_id' ], $list_element = true ) ) {
					unset( $ret[ 'organizer' ] );
				}
			}

			/**
			 * Event venue.
			 *
			 * Use is_valid_option_id() to check that the id value is not true, false, null, or 'none'.
			 */
			if ( isset( $event_opts[ 'event_location_id' ] ) && SucomUtil::is_valid_option_id( $event_opts[ 'event_location_id' ] ) ) {

				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log( 'adding place data for event_location_id ' . $event_opts[ 'event_location_id' ] );
				}

				if ( ! self::add_place_data( $ret[ 'location' ], $mod, $event_opts[ 'event_location_id' ], $list_element = false ) ) {
					unset( $ret[ 'location' ] );
				}

			} elseif ( $wpsso->debug->enabled ) {
				$wpsso->debug->log( 'event_location_id is empty or none' );
			}

			WpssoSchema::add_data_itemprop_from_assoc( $ret, $event_opts, array(
				'startDate' => 'event_start_date_iso',
				'endDate'   => 'event_end_date_iso',
			) );

			if ( ! empty( $event_opts[ 'event_offers' ] ) && is_array( $event_opts[ 'event_offers' ] ) ) {

				foreach ( $event_opts[ 'event_offers' ] as $event_offer ) {

					/**
					 * Setup the offer with basic itemprops.
					 */
					if ( is_array( $event_offer ) && false !== ( $offer = WpssoSchema::get_data_itemprop_from_assoc( $event_offer, array( 
						'name'          => 'offer_name',
						'url'           => 'offer_url',
						'price'         => 'offer_price',
						'priceCurrency' => 'offer_price_currency',
						'availability'  => 'offer_availability',	// In stock, Out of stock, Pre-order, etc.
						'validFrom'     => 'offer_valid_from_date',
						'validThrough'  => 'offer_valid_to_date',
					) ) ) ) {

						/**
						 * Add the complete offer.
						 */
						$ret[ 'offers' ][] = WpssoSchema::get_schema_type_context( 'https://schema.org/Offer', $offer );
					}
				}
			}

			$ret = apply_filters( $wpsso->lca . '_json_data_single_event', $ret, $mod, $event_id );

			if ( empty( $list_element ) ) {
				$json_data = $ret;
			} else {
				$json_data[] = $ret;
			}

			return 1;
		}

		public static function add_job_data( &$json_data, array $mod, $job_id = false, $list_element = false ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->mark();
			}

			$ret =& self::have_local_data( $json_data, $mod, 'job', $job_id, $list_element );

			if ( false !== $ret ) {	// 0 or 1 if data was retrieved from the local static cache.
				return $ret;
			}

			/**
			 * Maybe get options from Pro version integration modules.
			 */
			$job_opts = apply_filters( $wpsso->lca . '_get_job_options', false, $mod, $job_id );

			if ( ! empty( $job_opts ) ) {
				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log_arr( 'get_job_options filters returned', $job_opts );
				}
			}

			/**
			 * Override job options from filters with custom meta values (if any).
			 */
			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->log( 'merging default, filter, and custom option values' );
			}

			WpssoSchema::merge_custom_mod_opts( $mod, $job_opts, array( 'job' => 'schema_job' ) );

			/**
			 * If not adding a list element, inherit the existing schema type url (if one exists).
			 */
			list( $job_type_id, $job_type_url ) = self::get_type_id_url( $json_data, $job_opts, 'job_type', 'job.posting', $list_element );

			$ret = WpssoSchema::get_schema_type_context( $job_type_url );

			if ( empty( $job_opts[ 'job_title' ] ) ) {
				$job_opts[ 'job_title' ] = $wpsso->page->get_title( 0, '', $mod, true, false, true, 'schema_title', false );
			}

			/**
			 * Create and add ISO formatted date options.
			 */
			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->log( 'checking for custom job expire date and time' );
			}

			WpssoSchema::add_mod_opts_date_iso( $mod, $job_opts, array(
				'job_expire' => 'schema_job_expire',	// Prefix for date, time, timezone, iso.
			) );

			/**
			 * Add schema properties from the job options.
			 */
			WpssoSchema::add_data_itemprop_from_assoc( $ret, $job_opts, array(
				'title'        => 'job_title',
				'validThrough' => 'job_expire_iso',
			) );

			if ( isset( $job_opts[ 'job_salary' ] ) && is_numeric( $job_opts[ 'job_salary' ] ) ) {	// Allow for 0.

				$ret[ 'baseSalary' ] = WpssoSchema::get_schema_type_context( 'https://schema.org/MonetaryAmount' );

				WpssoSchema::add_data_itemprop_from_assoc( $ret[ 'baseSalary' ], $job_opts, array(
					'currency' => 'job_salary_currency',
				) );

				$ret[ 'baseSalary' ][ 'value' ] = WpssoSchema::get_schema_type_context( 'https://schema.org/QuantitativeValue' );

				WpssoSchema::add_data_itemprop_from_assoc( $ret[ 'baseSalary' ][ 'value' ], $job_opts, array(
					'value'    => 'job_salary',
					'unitText' => 'job_salary_period',
				) );
			}

			/**
			 * Allow for a preformatted employment types array.
			 */
			if ( ! empty( $job_opts[ 'job_empl_types' ] ) && is_array( $job_opts[ 'job_empl_types' ] ) ) {
				$ret[ 'employmentType' ] = $job_opts[ 'job_empl_types' ];
			}

			/**
			 * Add single employment type options (value must be non-empty).
			 */
			foreach ( SucomUtil::preg_grep_keys( '/^job_empl_type_/', $job_opts, false, '' ) as $empl_type => $checked ) {
				if ( ! empty( $checked ) ) {
					$ret[ 'employmentType' ][] = $empl_type;
				}
			}

			/**
			 * Job hiring organization.
			 *
			 * Use is_valid_option_id() to check that the id value is not true, false, null, or 'none'.
			 */
			if ( isset( $job_opts[ 'job_hiring_org_id' ] ) && SucomUtil::is_valid_option_id( $job_opts[ 'job_hiring_org_id' ] ) ) {	// Allow for 0.

				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log( 'adding organization data for job_hiring_org_id ' . $job_opts[ 'job_hiring_org_id' ] );
				}

				if ( ! self::add_organization_data( $ret[ 'hiringOrganization' ], $mod, $job_opts[ 'job_hiring_org_id' ], 'org_logo_url', false ) ) {
					unset( $ret[ 'hiringOrganization' ] );
				}

			} elseif ( $wpsso->debug->enabled ) {
				$wpsso->debug->log( 'job_hiring_org_id is empty or none' );
			}

			/**
			 * Job location.
			 *
			 * Use is_valid_option_id() to check that the id value is not true, false, null, or 'none'.
			 */
			if ( isset( $job_opts[ 'job_location_id' ] ) && SucomUtil::is_valid_option_id( $job_opts[ 'job_location_id' ] ) ) {	// Allow for 0.

				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log( 'adding place data for job_location_id ' . $job_opts[ 'job_location_id' ] );
				}

				if ( ! self::add_place_data( $ret[ 'jobLocation' ],
					$mod, $job_opts[ 'job_location_id' ], $list_element = false ) ) {

					unset( $ret[ 'jobLocation' ] );
				}

			} elseif ( $wpsso->debug->enabled ) {
				$wpsso->debug->log( 'job_location_id is empty or none' );
			}

			$ret = apply_filters( $wpsso->lca . '_json_data_single_job', $ret, $mod, $job_id );

			if ( empty( $list_element ) ) {
				$json_data = $ret;
			} else {
				$json_data[] = $ret;
			}

			return 1;
		}

		public static function get_offer_data( array $mod, array $mt_offer ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->mark();
			}

			/**
			 * Do not include an 'ean' property for the 'product:ean' value - there is no Schema 'ean' property.
			 */
			$offer = WpssoSchema::get_data_itemprop_from_assoc( $mt_offer, array( 
				'url'             => 'product:url',
				'name'            => 'product:title',
				'description'     => 'product:description',
				'category'        => 'product:category',
				'mpn'             => 'product:mfr_part_no',
				'sku'             => 'product:sku',	// Non-standard / internal meta tag.
				'gtin8'           => 'product:gtin8',
				'gtin12'          => 'product:gtin12',
				'gtin13'          => 'product:gtin13',
				'gtin14'          => 'product:gtin14',
				'itemCondition'   => 'product:condition',
				'availability'    => 'product:availability',
				'price'           => 'product:price:amount',
				'priceCurrency'   => 'product:price:currency',
				'priceValidUntil' => 'product:sale_price_dates:end',
			) );

			if ( false === $offer ) {	// Just in case.

				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log( 'exiting early: missing basic product meta tags' );
				}

				return false;
			}

			WpssoSchema::check_itemprop_content_map( $offer, 'itemCondition', 'product:condition' );

			WpssoSchema::check_itemprop_content_map( $offer, 'availability', 'product:availability' );

			/**
			 * Prevents a missing property warning from the Google validator.
			 */
			if ( empty( $offer[ 'priceValidUntil' ] ) ) {

				/**
				 * By default, define normal product prices (not on sale) as valid for 1 year.
				 */
				$valid_max_time  = SucomUtil::get_const( 'WPSSO_SCHEMA_PRODUCT_VALID_MAX_TIME', YEAR_IN_SECONDS );

				/**
				 * Only define once for all offers to allow for (maybe) a common value in the AggregateOffer markup.
				 */
				static $price_valid_until = null;

				if ( null === $price_valid_until ) {
					$price_valid_until = gmdate( 'c', time() + $valid_max_time );
				}
	
				$offer[ 'priceValidUntil' ] = $price_valid_until;
			}

			$quantity = WpssoSchema::get_data_itemprop_from_assoc( $mt_offer, array( 
				'value'    => 'product:quantity:value',
				'minValue' => 'product:quantity:minimum',
				'maxValue' => 'product:quantity:maximum',
				'unitCode' => 'product:quantity:unit_code',
				'unitText' => 'product:quantity:unit_text',
			) );

			if ( false !== $quantity ) {
				$offer[ 'eligibleQuantity' ] = WpssoSchema::get_schema_type_context( 'https://schema.org/QuantitativeValue ', $quantity );
			}

			$price_spec = WpssoSchema::get_data_itemprop_from_assoc( $mt_offer, array( 
				'price'                 => 'product:price:amount',
				'priceCurrency'         => 'product:price:currency',
				'priceValidUntil'       => 'product:sale_price_dates:end',
				'valueAddedTaxIncluded' => 'product:price:vat_included',
			) );

			if ( false !== $price_spec ) {

				if ( isset( $offer[ 'eligibleQuantity' ] ) ) {
					$price_spec[ 'eligibleQuantity' ] = $offer[ 'eligibleQuantity' ];
				}

				$offer[ 'priceSpecification' ] = WpssoSchema::get_schema_type_context( 'https://schema.org/PriceSpecification', $price_spec );
			}

			/**
			 * Returns 0 if no organization was found / added.
			 */
			if ( ! WpssoSchemaSingle::add_organization_data( $offer[ 'seller' ], $mod, 'site', 'org_logo_url', false ) ) {
				unset( $offer[ 'seller' ] );	// just in case
			}

			/**
			 * Add the product variation image.
			 */
			if ( ! empty( $mt_offer[ 'product:image:id' ] ) ) {

				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log( 'getting offer image ID ' . $mt_offer[ 'product:image:id' ] );
				}

				/**
				 * Set reference values for admin notices.
				 */
				if ( is_admin() ) {
					if ( ! empty( $offer[ 'url' ] ) ) {
						$wpsso->notice->set_ref( $offer[ 'url' ], $mod,
							__( 'adding schema for offer', 'wpsso-schema-json-ld' ) );
					}
				}

				$og_image = $wpsso->media->get_attachment_image( 1, $size_name = $wpsso->lca . '-schema',
					$mt_offer[ 'product:image:id' ], $check_dupes = false );

				if ( ! empty( $og_image ) ) {
					if ( ! WpssoSchema::add_og_image_list_data( $offer[ 'image' ], $og_image ) ) {
						unset( $offer[ 'image' ] );	// Prevent null assignment.
					}
				}

				/**
				 * Restore previous reference values for admin notices.
				 */
				if ( is_admin() ) {
					if ( ! empty( $offer[ 'url' ] ) ) {
						$wpsso->notice->unset_ref( $offer[ 'url' ] );
					}
				}
			}

			return WpssoSchema::get_schema_type_context( 'https://schema.org/Offer', $offer );
		}

		/**
		 * $org_id can be 'none', 'site', or a number (including 0).
		 * $logo_key can be 'org_logo_url' or 'org_banner_url' (600x60px image) for Articles.
		 * Do not provide localized option names - the method will fetch the localized values.
		 */
		public static function add_organization_data( &$json_data, $mod, $org_id = 'site', $logo_key = 'org_logo_url', $list_element = false ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->mark();
			}

			/**
			 * Check that the id value is not true, false, null, or 'none'.
			 */
			if ( ! SucomUtil::is_valid_option_id( $org_id ) ) {
				return 0;
			}

			$ret =& self::have_local_data( $json_data, $mod, 'organization', $org_id, $list_element );

			if ( false !== $ret ) {	// 0 or 1 if data was retrieved from the local static cache.
				return $ret;
			}

			/**
			 * Returned organization option values can change depending on the locale, but the option key names should NOT be localized.
			 *
			 * Example: 'org_banner_url' is a valid option key, but 'org_banner_url#fr_FR' is not.
			 */
			$org_opts = apply_filters( $wpsso->lca . '_get_organization_options', false, $mod, $org_id );

			if ( ! empty( $org_opts ) ) {
				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log_arr( 'get_organization_options filters returned', $org_opts );
				}
			} else {
				if ( $org_id === 'site' ) {
					if ( $wpsso->debug->enabled ) {
						$wpsso->debug->log( 'getting site organization options array' );
					}
					$org_opts = WpssoSchema::get_site_organization( $mod ); // Returns localized values (not the key names).
				} else {
					if ( $wpsso->debug->enabled ) {
						$wpsso->debug->log( 'exiting early: unknown org_id ' . $org_id );
					}
					return 0;
				}
			}

			/**
			 * If not adding a list element, inherit the existing schema type url (if one exists).
			 */
			list( $org_type_id, $org_type_url ) = self::get_type_id_url( $json_data, $org_opts, 'org_schema_type', 'organization', $list_element );

			$ret = WpssoSchema::get_schema_type_context( $org_type_url );

			/**
			 * Set the reference values for admin notices.
			 */
			if ( is_admin() ) {

				$sharing_url = $wpsso->util->get_sharing_url( $mod );

				$wpsso->notice->set_ref( $sharing_url, $mod, __( 'adding schema for organization', 'wpsso' ) );
			}

			/**
			 * Add schema properties from the organization options.
			 */
			WpssoSchema::add_data_itemprop_from_assoc( $ret, $org_opts, array(
				'url'           => 'org_url',
				'name'          => 'org_name',
				'alternateName' => 'org_name_alt',
				'description'   => 'org_desc',
				'email'         => 'org_email',
				'telephone'     => 'org_phone',
			) );

			/**
			 * Organization logo.
			 *
			 * $logo_key can be false, 'org_logo_url' (default), or 'org_banner_url' (600x60px image) for Articles.
			 */
			if ( ! empty( $logo_key ) ) {

				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log( 'adding image from ' . $logo_key . ' option' );
				}

				if ( ! empty( $org_opts[ $logo_key ] ) ) {
					if ( ! WpssoSchema::add_og_single_image_data( $ret[ 'logo' ], $org_opts, $logo_key, false ) ) {	// $list_element is false.
						unset( $ret[ 'logo' ] );	// Prevent null assignment.
					}
				}

				if ( empty( $ret[ 'logo' ] ) ) {

					if ( $wpsso->debug->enabled ) {
						$wpsso->debug->log( 'organization ' . $logo_key . ' image is missing and required' );
					}

					if ( $wpsso->notice->is_admin_pre_notices() && ( ! $mod[ 'is_post' ] || $mod[ 'post_status' ] === 'publish' ) ) {

						if ( $logo_key === 'org_logo_url' ) {

							$error_msg = __( 'The "%1$s" Organization Logo image is missing and required for the Schema %2$s markup.',
								'wpsso' );

							$wpsso->notice->err( sprintf( $error_msg, $ret[ 'name' ], $org_type_url ) );

						} elseif ( $logo_key === 'org_banner_url' ) {

							$error_msg = __( 'The "%1$s" Organization Banner (600x60px) image is missing and required for the Schema %2$s markup.',
								'wpsso' );

							$wpsso->notice->err( sprintf( $error_msg, $ret[ 'name' ], $org_type_url ) );
						}
					}
				}
			}

			/**
			 * Place / location properties.
			 *
			 * Use is_valid_option_id() to check that the id value is not true, false, null, or 'none'.
			 */
			if ( isset( $org_opts[ 'org_place_id' ] ) && SucomUtil::is_valid_option_id( $org_opts[ 'org_place_id' ] ) ) {

				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log( 'adding place / location properties' );
				}

				/**
				 * Check for a custom place id that might have precedence.
				 *
				 * 'plm_place_id' can be 'none', 'custom', or numeric (including 0).
				 */
				if ( ! empty( $mod[ 'obj' ] ) ) {
					$place_id = $mod[ 'obj' ]->get_options( $mod[ 'id' ], 'plm_place_id' );
				} else {
					$place_id = null;
				}

				if ( null === $place_id ) {
					$place_id = $org_opts[ 'org_place_id' ];
				} else {
					if ( $wpsso->debug->enabled ) {
						$wpsso->debug->log( 'overriding org_place_id ' . $org_opts[ 'org_place_id' ] . ' with plm_place_id ' . $place_id );
					}
				}

				if ( ! self::add_place_data( $ret[ 'location' ], $mod, $place_id, $list_element = false ) ) {
					unset( $ret[ 'location' ] );	// Prevent null assignment.
				}
			}

			/**
			 * Google's knowledge graph.
			 */
			$org_opts[ 'org_sameas' ] = isset( $org_opts[ 'org_sameas' ] ) ? $org_opts[ 'org_sameas' ] : array();
			$org_opts[ 'org_sameas' ] = apply_filters( $wpsso->lca . '_json_data_single_organization_sameas', $org_opts[ 'org_sameas' ], $mod, $org_id );

			if ( ! empty( $org_opts[ 'org_sameas' ] ) && is_array( $org_opts[ 'org_sameas' ] ) ) {	// Just in case.
				foreach ( $org_opts[ 'org_sameas' ] as $url ) {
					if ( ! empty( $url ) ) {	// Just in case.
						$ret[ 'sameAs' ][] = SucomUtil::esc_url_encode( $url );
					}
				}
			}

			if ( ! empty( $org_type_id ) && $org_type_id !== 'organization' && 
				$wpsso->schema->is_schema_type_child( $org_type_id, 'local.business' ) ) {

				WpssoSchema::organization_to_localbusiness( $ret );
			}

			$ret = apply_filters( $wpsso->lca . '_json_data_single_organization', $ret, $mod, $org_id );

			/**
			 * Restore previous reference values for admin notices.
			 */
			if ( is_admin() ) {
				$wpsso->notice->unset_ref( $sharing_url );
			}

			if ( empty( $list_element ) ) {
				$json_data = $ret;
			} else {
				$json_data[] = $ret;
			}

			return 1;
		}

		/**
		 * A $user_id argument is required.
		 */
		public static function add_person_data( &$json_data, $mod, $user_id, $list_element = true ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->mark();
			}

			$ret =& self::have_local_data( $json_data, $mod, 'person', $user_id, $list_element );

			if ( false !== $ret ) {	// 0 or 1 if data was retrieved from the local static cache.
				return $ret;
			}

			$size_name   = $wpsso->lca . '-schema';

			/**
			 * Maybe get options from Pro version integration modules.
			 */
			$person_opts = apply_filters( $wpsso->lca . '_get_person_options', false, $mod, $user_id );

			if ( ! empty( $person_opts ) ) {

				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log_arr( 'get_person_options filters returned', $person_opts );
				}

			} else {

				if ( empty( $user_id ) || $user_id === 'none' ) {

					if ( $wpsso->debug->enabled ) {
						$wpsso->debug->log( 'exiting early: empty user_id' );
					}

					return 0;

				} elseif ( empty( $wpsso->m[ 'util' ][ 'user' ] ) ) {

					if ( $wpsso->debug->enabled ) {
						$wpsso->debug->log( 'exiting early: empty user module' );
					}

					return 0;

				} else {

					if ( $wpsso->debug->enabled ) {
						$wpsso->debug->log( 'getting user module for user_id ' . $user_id );
					}

					$user_mod = $wpsso->m[ 'util' ][ 'user' ]->get_mod( $user_id );
				}

				$user_desc = $user_mod[ 'obj' ]->get_options_multi( $user_id, $md_key = array( 'schema_desc', 'seo_desc', 'og_desc' ) );

				if ( empty( $user_desc ) ) {
					$user_desc = $user_mod[ 'obj' ]->get_author_meta( $user_id, 'description' );
				}

				/**
				 * Remove shortcodes, strip html, etc.
				 */
				$user_desc = $wpsso->util->cleanup_html_tags( $user_desc );

				$user_sameas = array();

				foreach ( WpssoUser::get_user_id_contact_methods( $user_id ) as $cm_id => $cm_label ) {

					$url = $user_mod[ 'obj' ]->get_author_meta( $user_id, $cm_id );

					if ( empty( $url ) ) {
						continue;
					} elseif ( $cm_id === $wpsso->options[ 'plugin_cm_twitter_name' ] ) {	// Convert twitter name to url.
						$url = 'https://twitter.com/' . preg_replace( '/^@/', '', $url );
					}

					if ( false !== filter_var( $url, FILTER_VALIDATE_URL ) ) {
						$user_sameas[] = $url;
					}
				}

				$person_opts = array(
					'person_type'      => 'person',
					'person_url'       => $user_mod[ 'obj' ]->get_author_website( $user_id, 'url' ),
					'person_name'      => $user_mod[ 'obj' ]->get_author_meta( $user_id, $wpsso->options[ 'seo_author_name' ] ),
					'person_desc'      => $user_desc,
					'person_job_title' => $user_mod[ 'obj' ]->get_options( $user_id, 'schema_person_job_title' ),
					'person_og_image'  => $user_mod[ 'obj' ]->get_og_images( 1, $size_name, $user_id, false ),
					'person_sameas'    => $user_sameas,
				);
			}

			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->log_arr( 'person options', $person_opts );
			}

			/**
			 * If not adding a list element, inherit the existing schema type url (if one exists).
			 */
			list( $person_type_id, $person_type_url ) = self::get_type_id_url( $json_data, $person_opts, 'person_type', 'person', $list_element );

			$ret = WpssoSchema::get_schema_type_context( $person_type_url );

			WpssoSchema::add_data_itemprop_from_assoc( $ret, $person_opts, array(
				'url'         => 'person_url',
				'name'        => 'person_name',
				'description' => 'person_desc',
				'jobTitle'    => 'person_job_title',
				'email'       => 'person_email',
				'telephone'   => 'person_phone',
			) );

			/**
			 * Images
			 */
			if ( ! empty( $person_opts[ 'person_og_image' ] ) ) {
				if ( ! WpssoSchema::add_og_image_list_data( $ret[ 'image' ], $person_opts[ 'person_og_image' ] ) ) {
					unset( $ret[ 'image' ] );	// Prevent null assignment.
				}
			}

			/**
			 * Google's knowledge graph.
			 */
			$person_opts[ 'person_sameas' ] = isset( $person_opts[ 'person_sameas' ] ) ? $person_opts[ 'person_sameas' ] : array();
			$person_opts[ 'person_sameas' ] = apply_filters( $wpsso->lca . '_json_data_single_person_sameas', $person_opts[ 'person_sameas' ], $mod, $user_id );

			if ( ! empty( $person_opts[ 'person_sameas' ] ) && is_array( $person_opts[ 'person_sameas' ] ) ) {	// Just in case.
				foreach ( $person_opts[ 'person_sameas' ] as $url ) {
					if ( ! empty( $url ) ) {	// Just in case.
						$ret[ 'sameAs' ][] = SucomUtil::esc_url_encode( $url );
					}
				}
			}

			$ret = apply_filters( $wpsso->lca . '_json_data_single_person', $ret, $mod, $user_id );

			if ( empty( $list_element ) ) {
				$json_data = $ret;
			} else {
				$json_data[] = $ret;
			}

			return 1;
		}

		public static function add_place_data( &$json_data, $mod, $place_id = false, $list_element = false ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->mark();
			}

			$ret =& self::have_local_data( $json_data, $mod, 'place', $place_id, $list_element );

			if ( false !== $ret ) {	// 0 or 1 if data was retrieved from the local static cache.
				return $ret;
			}

			$size_name  = $wpsso->lca . '-schema';

			/**
			 * Maybe get options from Pro version integration modules.
			 */
			$place_opts = apply_filters( $wpsso->lca . '_get_place_options', false, $mod, $place_id );

			if ( ! empty( $place_opts ) ) {
				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log_arr( 'get_place_options filters returned', $place_opts );
				}
			} else {
				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log( 'exiting early: empty place options' );
				}
				return 0;
			}

			/**
			 * If not adding a list element, inherit the existing schema type url (if one exists).
			 */
			list( $place_type_id, $place_type_url ) = self::get_type_id_url( $json_data, $place_opts, 'place_schema_type', 'place', $list_element );

			$ret = WpssoSchema::get_schema_type_context( $place_type_url );

			/**
			 * Set reference values for admin notices.
			 */
			if ( is_admin() ) {

				$sharing_url = $wpsso->util->get_sharing_url( $mod );

				$wpsso->notice->set_ref( $sharing_url, $mod, __( 'adding schema for place', 'wpsso' ) );
			}

			/**
			 * Add schema properties from the place options.
			 */
			WpssoSchema::add_data_itemprop_from_assoc( $ret, $place_opts, array(
				'url'                => 'place_url',
				'name'               => 'place_name',
				'alternateName'      => 'place_name_alt',
				'description'        => 'place_desc',
				'telephone'          => 'place_phone',
				'currenciesAccepted' => 'place_currencies_accepted',
				'paymentAccepted'    => 'place_payment_accepted',
				'priceRange'         => 'place_price_range',
			) );

			/**
			 * Property:
			 *	address as https://schema.org/PostalAddress
			 */
			$postal_address = array();

			if ( WpssoSchema::add_data_itemprop_from_assoc( $postal_address, $place_opts, array(
				'name'                => 'place_name', 
				'streetAddress'       => 'place_street_address', 
				'postOfficeBoxNumber' => 'place_po_box_number', 
				'addressLocality'     => 'place_city',
				'addressRegion'       => 'place_state',
				'postalCode'          => 'place_zipcode',
				'addressCountry'      => 'place_country',	// Alpha2 country code.
			) ) ) {
				$ret[ 'address' ] = WpssoSchema::get_schema_type_context( 'https://schema.org/PostalAddress', $postal_address );
			}

			/**
			 * Property:
			 *	geo as https://schema.org/GeoCoordinates
			 */
			$geo = array();

			if ( WpssoSchema::add_data_itemprop_from_assoc( $geo, $place_opts, array(
				'elevation' => 'place_altitude', 
				'latitude'  => 'place_latitude',
				'longitude' => 'place_longitude',
			) ) ) {
				$ret[ 'geo' ] = WpssoSchema::get_schema_type_context( 'https://schema.org/GeoCoordinates', $geo );
			}

			/**
			 * Property:
			 *	openingHoursSpecification as https://schema.org/OpeningHoursSpecification
			 */
			$opening_hours = array();

			foreach ( $wpsso->cf[ 'form' ][ 'weekdays' ] as $day => $label ) {

				if ( ! empty( $place_opts['place_day_' . $day] ) ) {

					$dayofweek = array(
						'@context'  => 'https://schema.org',
						'@type'     => 'OpeningHoursSpecification',
						'dayOfWeek' => $label,
					);

					foreach ( array(
						'opens'        => 'place_day_' . $day . '_open',
						'closes'       => 'place_day_' . $day . '_close',
						'validFrom'    => 'place_season_from_date',
						'validThrough' => 'place_season_to_date',
					) as $prop_name => $opt_key ) {

						if ( isset( $place_opts[ $opt_key ] ) && $place_opts[ $opt_key ] !== '' ) {
							$dayofweek[ $prop_name ] = $place_opts[ $opt_key ];
						}
					}

					$opening_hours[] = $dayofweek;
				}
			}

			if ( ! empty( $opening_hours ) ) {
				$ret[ 'openingHoursSpecification' ] = $opening_hours;
			}

			/**
			 * FoodEstablishment schema type properties
			 */
			if ( ! empty( $place_opts[ 'place_schema_type' ] ) && $place_opts[ 'place_schema_type' ] !== 'none' ) {

				if ( $wpsso->schema->is_schema_type_child( $place_opts[ 'place_schema_type' ], 'food.establishment' ) ) {

					foreach ( array(
						'acceptsReservations' => 'place_accept_res',
						'hasMenu'             => 'place_menu_url',
						'servesCuisine'       => 'place_cuisine',
					) as $prop_name => $opt_key ) {

						if ( $opt_key === 'place_accept_res' ) {
							$ret[ $prop_name ] = empty( $place_opts[ $opt_key ] ) ? 'false' : 'true';
						} elseif ( isset( $place_opts[ $opt_key ] ) ) {
							$ret[ $prop_name ] = $place_opts[ $opt_key ];
						}
					}
				}
			}

			if ( ! empty( $place_opts[ 'place_order_urls' ] ) ) {

				foreach ( SucomUtil::explode_csv( $place_opts[ 'place_order_urls' ] ) as $order_url ) {

					if ( ! empty( $order_url ) ) {	// Just in case.

						$ret[ 'potentialAction' ][] = array(
							'@context' => 'https://schema.org',
							'@type'    => 'OrderAction',
							'target'   => $order_url,
						);
					}
				}
			}

			/**
			 * Image.
			 */
			if ( ! empty( $place_opts[ 'place_img_id' ] ) || ! empty( $place_opts[ 'place_img_url' ] ) ) {

				$mt_image = $wpsso->media->get_opts_single_image( $place_opts, $size_name, 'place_img' );

				if ( ! WpssoSchema::add_og_single_image_data( $ret[ 'image' ], $mt_image, 'og:image', true ) ) {	// $list_element is true.
					unset( $ret[ 'image' ] );	// Prevent null assignment.
				}
			}

			$ret = apply_filters( $wpsso->lca . '_json_data_single_place', $ret, $mod, $place_id );

			/**
			 * Restore previous reference values for admin notices.
			 */
			if ( is_admin() ) {
				$wpsso->notice->unset_ref( $sharing_url );
			}

			if ( empty( $list_element ) ) {
				$json_data = $ret;
			} else {
				$json_data[] = $ret;
			}

			return 1;
		}

		/**
		 * If not adding a list element, then inherit the existing
		 * schema type url (if one exists).
		 */
		public static function get_type_id_url( $json_data, $type_opts, $opt_key, $default_id, $list_element = false ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->mark();
			}

			$single_type_id   = false;
			$single_type_url  = $list_element ? false : WpssoSchema::get_data_type_url( $json_data );
			$single_type_from = 'inherited';

			if ( false === $single_type_url ) {

				/**
				 * $type_opts may be false, null, or an array.
				 */
				if ( empty( $type_opts[ $opt_key ] ) || $type_opts[ $opt_key ] === 'none' ) {

					$single_type_id   = $default_id;
					$single_type_url  = $wpsso->schema->get_schema_type_url( $default_id );
					$single_type_from = 'default';

				} else {

					$single_type_id   = $type_opts[ $opt_key ];
					$single_type_url  = $wpsso->schema->get_schema_type_url( $single_type_id, $default_id );
					$single_type_from = 'options';
				}
			}

			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->log( 'using ' . $single_type_from . ' single type url: ' . $single_type_url );
			}

			return array( $single_type_id, $single_type_url );
		}

		/**
		 * Adds a $json_data array element and returns a reference
		 * (false, 0 or 1).
		 *
		 * If the local static cache does not contain an existing
		 * entry, a new cache entry is created (as false) and a
		 * reference to that cache entry is returned.
		 */
		private static function &have_local_data( &$json_data, $mod, $single_name, $single_id, $list_element = false ) {

			$wpsso =& Wpsso::get_instance();

			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->mark();
			}

			$single_added = 0;
			$action_name  = 'creating';

			if ( $single_id === 'none' ) {

				if ( $wpsso->debug->enabled ) {
					$wpsso->debug->log( 'exiting early: ' . $single_name . ' id is ' . $single_id );
				}

				return $single_added;
			}

			static $local_cache = array();	// Cache for single page load.

			if ( isset( $local_cache[ $mod[ 'name' ] ][ $mod[ 'id' ] ][ $single_name ][ $single_id ] ) ) {

				$action_name = 'using';
				$single_data =& $local_cache[ $mod[ 'name' ] ][ $mod[ 'id' ] ][ $single_name ][ $single_id ];

				if ( false === $single_data ) {

					$single_added = 0;

				} else {

					if ( empty( $list_element ) ) {
						$json_data = $single_data;
					} else {
						$json_data[] = $single_data;
					}

					$single_added = 1;
				}

			} else {

				$local_cache[ $mod[ 'name' ] ][ $mod[ 'id' ] ][ $single_name ][ $single_id ] = false;

				$single_added =& $local_cache[ $mod[ 'name' ] ][ $mod[ 'id' ] ][ $single_name ][ $single_id ];	// Return a reference to false.
			}

			if ( $wpsso->debug->enabled ) {
				$wpsso->debug->log( $action_name . ' ' . $single_name . ' cache data for mod id ' . $mod[ 'id' ] . 
					' / ' . $single_name . ' id ' . ( false === $single_id ? 'is false' : $single_id ) );
			}

			return $single_added;	// Return a reference to 0, 1, or false.
		}
	}
}
