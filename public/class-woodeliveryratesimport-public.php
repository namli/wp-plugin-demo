<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.phpninja.info/
 * @since      1.0.0
 *
 * @package    Woodeliveryratesimport
 * @subpackage Woodeliveryratesimport/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Woodeliveryratesimport
 * @subpackage Woodeliveryratesimport/public
 * @author     Phpninga <contacto@phpninja.info>
 */

use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;


global $woocommerce;
//global $wpdb;

class Woodeliveryratesimport_Public
{

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Woodeliveryratesimport_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Woodeliveryratesimport_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		//	wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/woodeliveryratesimport-public.css', array(), $this->version, 'all');
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Woodeliveryratesimport_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Woodeliveryratesimport_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		//wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/woodeliveryratesimport-public.js', array('jquery'), $this->version, false);
	}

	public function wdri_query_vars($query_vars)
	{
		$query_vars[] = 'wdri_import_csv';
		return $query_vars;
	}

	public function wdri_parse_request(&$wp)
	{
		if (array_key_exists('wdri_import_csv', $wp->query_vars)) {
			echo '<h1>We are run import</h1>';

			$filename = ABSPATH . "shiprate/Portes_AMC.csv";
			if (!is_readable($filename)) {
				echo 'File not exist: ';
				die($filename);
			};

			// delete old zones
			$oldZones = get_option('wdri_zones');
			if ($oldZones) {
				$this->deleteOldZones($oldZones);
			}

			$spreadsheetFromCsv = $this->getDataFromFile($filename);

			$worksheet = $spreadsheetFromCsv->getActiveSheet();


			$startArr = $worksheet->toArray();

			// // delete header
			array_shift($startArr);

			// //reverse array
			$startArr = array_reverse($startArr);

			$resArr = [];
			foreach ($startArr as $line) {
				$deliveryZone = $this->getDeliveryZone($line[1], $line[3]);

				if ($deliveryZone->countryCode == '') {
					continue;
				}

				$deliveryZone->zoneName = strtolower(sanitize_file_name($deliveryZone->countryName)) . $deliveryZone->regCode;

				if (!$resArr[$deliveryZone->zoneName] && $deliveryZone->zoneName != '') {
					$resArr[$deliveryZone->zoneName] = [];
				}



				if ($deliveryZone->countryCode != '' && $deliveryZone->regCode != '' && !$resArr[$deliveryZone->zoneName]['location_code']) {
					$resArr[$deliveryZone->zoneName]['location_code'] = $deliveryZone->countryCode . ':' . $deliveryZone->regCode;
					$resArr[$deliveryZone->zoneName]['location_type'] = 'state';
				}


				if ($deliveryZone->countryCode != '' && $deliveryZone->regCode == '' && !$resArr[$deliveryZone->zoneName]['location_code']) {
					$resArr[$deliveryZone->zoneName]['location_code'] = $deliveryZone->countryCode;
					$resArr[$deliveryZone->zoneName]['location_type'] = 'country';
				}

				if (!$resArr[$deliveryZone->zoneName]['method_id'] && $deliveryZone->countryCode != '') {
					$resArr[$deliveryZone->zoneName]['method_id'] = 'super_shipping';
				}

				if ($line[1] == '1' && !$resArr[$deliveryZone->zoneName]['shipping_class']) {
					$resArr[$deliveryZone->zoneName]['shipping_class'] = 'nacional';
				}

				if ($line[1] != '1' && !$resArr[$deliveryZone->zoneName]['shipping_class']) {
					$resArr[$deliveryZone->zoneName]['shipping_class'] = 'internacional';
				}

				$resArr[$deliveryZone->zoneName]['data'][] = [
					'shipping_class' => $resArr[$deliveryZone->zoneName]['shipping_class'],
					'conditional' => 1,
					'range' => ['min' => $line[4], 'max' => $line[5]],
					'cost' => $line[6],
					'cost_per_additional_unit' => 0
				];
			}
			//echo '<pre>' . var_export($resArr, true) . '</pre>';

			foreach ($resArr as $key => $value) {
				[$deliveryZoneId, $deliveryMethodId] = $this->createShipZone('wdri_' . $key, $value['location_code'], $value['location_type'], $value['method_id']);
				$zones[] = [$deliveryZoneId, $deliveryMethodId];
				$this->update_shipping_rules($deliveryMethodId, $value['data']);
			}

			update_option('wdri_zones', $zones);
			//echo '<pre>' . var_export($zones, true) . '</pre>';
			echo  'Delete file: ' . unlink($filename);
			echo '<h1 style="color:green;">All zones and rates imported</h1>';



			exit();
		}

		return;
	}

	public function deleteOldZones(array $oldZones = null)
	{
		foreach ($oldZones as $value) {
			WC_Shipping_Zones::delete_zone($value[0]);
		}
	}

	public function getDataFromFile(String $file = null)
	{

		$CsvReader = new CsvReader();
		$CsvReader->setDelimiter(';');
		$CsvReader->setEnclosure('');
		$CsvReader->setSheetIndex(0);
		$spreadsheetFromCSV = $CsvReader->load($file);
		return $spreadsheetFromCSV;
	}

	public function getDeliveryZone(String $countryId = null, String $regId = null)
	{
		$rez = new stdClass();
		$countries_obj   = new WC_Countries();
		$countries   = $countries_obj->get_countries();
		$region = $countries_obj->get_states('ES');

		switch ($countryId) {
			case '1': //SPAIN
				$rez->countryCode = 'ES';
				$rez->countryId = '1';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '2': //FRANCE
				$rez->countryCode = 'FR';
				$rez->countryId = '2';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '3': //UNITED KINGDOM
				$rez->countryCode = 'GB';
				$rez->countryId = '3';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '4': //GERMANY
				$rez->countryCode = 'DE';
				$rez->countryId = '4';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '5': //ITALY
				$rez->countryCode = 'IT';
				$rez->countryId = '5';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '7': //SWITZERLAND
				$rez->countryCode = 'CH';
				$rez->countryId = '7';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '8': //AUSTRIA
				$rez->countryCode = 'AT';
				$rez->countryId = '8';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '9': //SWEDEN
				$rez->countryCode = 'SE';
				$rez->countryId = '9';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '10': //NORWAY
				$rez->countryCode = 'NO';
				$rez->countryId = '10';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '11': //BELGIUM
				$rez->countryCode = 'BE';
				$rez->countryId = '11';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '12': //NETHERLANDS
				$rez->countryCode = 'NL';
				$rez->countryId = '12';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '13': //LUXEMBOURG
				$rez->countryCode = 'LU';
				$rez->countryId = '13';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '14': //PORTUGAL
				$rez->countryCode = 'PT';
				$rez->countryId = '14';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '15': //DENMARK
				$rez->countryCode = 'DK';
				$rez->countryId = '15';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '16': //LIECHTENSTEIN
				$rez->countryCode = 'LI';
				$rez->countryId = '16';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '17': //IRELAND
				$rez->countryCode = 'IE';
				$rez->countryId = '17';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '18': // Brazil
				$rez->countryCode = 'BR';
				$rez->countryId = '18';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '19': //CANADA
				$rez->countryCode = 'CA';
				$rez->countryId = '19';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '20': //UNITED STATES
				$rez->countryCode = 'US';
				$rez->countryId = '20';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '22': //HUNGARY
				$rez->countryCode = 'HU';
				$rez->countryId = '22';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '23': //SLOVAKIA
				$rez->countryCode = 'SK';
				$rez->countryId = '23';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '24': //POLAND
				$rez->countryCode = 'PL';
				$rez->countryId = '24';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '26': //CZECH REPUBLIC
				$rez->countryCode = 'CZ';
				$rez->countryId = '26';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '100': //ANDORRA
				$rez->countryCode = 'DE';
				$rez->countryId = '4';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '101': //UNITED ARAB EMIRATES
				$rez->countryCode = 'ES';
				$rez->countryId = '1';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '105': //ALBANIA
				$rez->countryCode = 'FR';
				$rez->countryId = '2';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '110': //ARGENTINA
				$rez->countryCode = 'GB';
				$rez->countryId = '3';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '112': //AUSTRALIA
				$rez->countryCode = 'DE';
				$rez->countryId = '4';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '115': //BOSNIA AND HERZEGOVINA
				$rez->countryCode = 'ES';
				$rez->countryId = '1';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '119': //BULGARIA
				$rez->countryCode = 'FR';
				$rez->countryId = '2';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '130': //BELARUS
				$rez->countryCode = 'GB';
				$rez->countryId = '3';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '138': //CHILE
				$rez->countryCode = 'DE';
				$rez->countryId = '4';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '140': //CHINA
				$rez->countryCode = 'DE';
				$rez->countryId = '4';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '141': //COLOMBIA
				$rez->countryCode = 'ES';
				$rez->countryId = '1';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '142': //COSTA RICA
				$rez->countryCode = 'FR';
				$rez->countryId = '2';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '143': // SERBIA AND MONTENEGRO
				$rez->countryCode = 'GB';
				$rez->countryId = '3';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '147': //CYPRUS
				$rez->countryCode = 'CY';
				$rez->countryId = '147';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '152': //ALGERIA
				$rez->countryCode = 'DZ';
				$rez->countryId = '152';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '154': //ESTONIA
				$rez->countryCode = 'EE';
				$rez->countryId = '154';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '159':	//FINLAND
				$rez->countryCode = 'FI';
				$rez->countryId = '159';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '173':	//GUADELOUPE
				$rez->countryCode = 'GP';
				$rez->countryId = '173';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '175':	//GREECE
				$rez->countryCode = 'GR';
				$rez->countryId = '175';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '184':	//CROATIA
				$rez->countryCode = 'HR';
				$rez->countryId = '184';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '187':	//INDONESIA
				$rez->countryCode = 'ID';
				$rez->countryId = '187';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '188':	//ISRAEL
				$rez->countryCode = 'IL';
				$rez->countryId = '188';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '189':	//INDIA
				$rez->countryCode = 'IN';
				$rez->countryId = '189';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '192':	//IRAN, ISLAMIC REPUBLIC OF
				$rez->countryCode = 'IR';
				$rez->countryId = '192';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '193':	//ICELAND
				$rez->countryCode = 'IS';
				$rez->countryId = '193';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '214':	//LITHUANIA
				$rez->countryCode = 'LT';
				$rez->countryId = '214';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '215':	//LATVIA
				$rez->countryCode = 'LV';
				$rez->countryId = '215';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '217':	//MOROCCO
				$rez->countryCode = 'MA';
				$rez->countryId = '217';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '218':	//MONACO
				$rez->countryCode = 'MC';
				$rez->countryId = '218';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '219':	//MOLDOVA, REPUBLIC OF
				$rez->countryCode = 'MD';
				$rez->countryId = '219';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '222':	//MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF
				$rez->countryCode = 'MK';
				$rez->countryId = '222';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '231':	//MALTA
				$rez->countryCode = 'MT';
				$rez->countryId = '231';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '235':	//MEXICO
				$rez->countryCode = 'MX';
				$rez->countryId = '235';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '242':	//NIGERIA
				$rez->countryCode = 'NG';
				$rez->countryId = '242';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '247':	//NEW ZEALAND
				$rez->countryCode = 'NZ';
				$rez->countryId = '247';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '264':	//ROMANIA
				$rez->countryCode = 'RO';
				$rez->countryId = '264';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '265':	//RUSSIAN FEDERATION
				$rez->countryCode = 'RU';
				$rez->countryId = '265';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '271':	//SINGAPORE
				$rez->countryCode = 'SG';
				$rez->countryId = '271';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '273':	//SLOVENIA
				$rez->countryCode = 'SI';
				$rez->countryId = '273';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '277':	//SAN MARINO
				$rez->countryCode = 'SM';
				$rez->countryId = '277';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '289':	//THAILAND
				$rez->countryCode = 'TH';
				$rez->countryId = '289';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '296':	//TURKEY
				$rez->countryCode = 'TR';
				$rez->countryId = '296';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '301':	// Ukrain
				$rez->countryCode = 'UA';
				$rez->countryId = '301';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '304':	//URUGUAY
				$rez->countryCode = 'UY';
				$rez->countryId = '304';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '308':	//VENEZUELA
				$rez->countryCode = 'VE';
				$rez->countryId = '308';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			case '317':	//SOUTH AFRICA
				$rez->countryCode = 'ZA';
				$rez->countryId = '317';
				$rez->countryName = $countries[$rez->countryCode];
				break;
			default:
				$rez->countryCode = '';
				$rez->countryId = '';
				$rez->countryName = '';
				break;
		}

		if ($countryId == '1') {
			switch ($regId) {
				case '9999':
					$rez->regCode = '';
					$rez->regName = '';
					break;
				case '7':
					$rez->regCode = 'PM';
					$rez->regName = $region[$rez->regCode];
					break;
				case '35':
					$rez->regCode = 'GC';
					$rez->regName = $region[$rez->regCode];
					break;
				case '38':
					$rez->regCode = 'TF';
					$rez->regName = $region[$rez->regCode];
					break;
				case '51':
					$rez->regCode = 'CE';
					$rez->regName = $region[$rez->regCode];
					break;
				case '52':
					$rez->regCode = 'ML';
					$rez->regName = $region[$rez->regCode];
					break;
				default:
					$rez->regCode = '';
					$rez->regName = '';
					break;
			}
		} else {
			$rez->regCode = '';
			$rez->regName = '';
		}


		return $rez;
	}



	public function createShipZone(String $name = null, String $location_code, String $location_type, String $method_type)
	{
		$new_zone = new WC_Shipping_Zone();
		$new_zone->set_zone_name($name);

		$new_zone->add_location($location_code, $location_type);

		$new_zone->save();

		$methodId = $new_zone->add_shipping_method($method_type);

		return [$new_zone->get_id(), $methodId];
	}

	/**
	 * Update shipping rules with formated data
	 *
	 * @return void
	 */
	static public function update_shipping_rules($method_id, $data)
	{
		$shipping_method = new WooCommerce_Super_Shipping($method_id);
		$shipping_method->update_option('shipping_rules', $data);
	}
}
