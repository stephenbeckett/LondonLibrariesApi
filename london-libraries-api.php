<?php

//What: A quick & dirty API for the London Libraries website - https://www.londonlibraries.gov.uk
//Who: Stephen Beckett - steve@stevebeckett.com twitter.com/stephenbeckett
//Where: https://github.com/stephenbeckett/LondonLibrariesApi/

//v1.1
//Contribute: https://github.com/stephenbeckett/LondonLibrariesApi/

//require_once('london-libraries-exceptions.php');
require_once('simple_html_dom.php');

class LondonLibrariesApi {
	protected $membershipId, $pin, $surname;
	protected $username;
	protected $libraryUrl, $loggedIn;
	protected $cookies;
	public $error;
	
	function __construct() {
		$this->libraryUrl = 'https://www.londonlibraries.gov.uk';
		$this->sessionID = $membershipId = $pin = '';
		$this->loggedIn = false;
		$this->cookies = array();
	}
	
	//API METHODS
	//Auth
	public function login($membershipId, $pin) {
		//Call login form once to get viewstate and eventvalidation fields
		$html = file_get_contents($this->libraryUrl.'/00_002_login.aspx');
		$this->setCookie('ASP.NET_SessionId', substr($http_response_header[6], 30, 24)); //Not v robust, could iterate headers + regex
		$dom = str_get_html($html);
		
		$AspViewState = $dom->find('#__VIEWSTATE', 0)->attr['value'];
		$AspEventValidation = $dom->find('#__EVENTVALIDATION', 0)->attr['value'];
		
		//Submit with password & username (& ASP meta data)
		$postData = array(
			'__VIEWSTATE' => $AspViewState,
			'__EVENTVALIDATION' => $AspEventValidation,
			'ctl00$ContentPlaceCenterContent$login2$username' => $membershipId,
			'ctl00$ContentPlaceCenterContent$login2$password' => $pin,
			'ctl00$ContentPlaceCenterContent$login2$LoginButton' => 'Sign in'
		);
		
		$html = file_get_contents($this->libraryUrl.'/00_002_login.aspx', false, $this->buildContext($postData));
		$dom = str_get_html($html);
		
		//Check if logged in
		if ($dom->find('#ctl00_LoginInfoControl1 span', 0)) {
			//Log in succeeded 
			preg_match('/=([a-z0-9]*);/i', $http_response_header[7], $matches); //Get viewpoint cookie
			$this->setCookie('viewpoint', $matches[1]);
			$surname = $dom->find('.borrowername', 0)->innertext; //Current user surname
			$this->setMemberDetails($membershipId, $pin, $surname);
			$this->loggedIn = true;
			return true;
		} else {
			//Log in failed
			$this->error = $dom->find('#ctl00_ContentPlaceCenterContent_login2 .error', 0)->innertext; //Get error message (throw here?)
			$this->loggedIn = false;
			return false;
		}
	}
	
	public function logout() {
		//TODO Sign out from site
		//Clear session id
		$this->loggedIn = false;
	}
	
	public function getCurrentLoans() {
		//Get page
		$dom = file_get_html($this->libraryUrl.'/01_YourAccount/01_002_YourLoans.aspx', false, $this->buildContext());
		
		$rentalsDom = $dom->find('.TitleListResultsItemContainerStyle4');
		$rentals = array();
		$i = 0;
		
		//Iterate over each rental item, add to array
		foreach ($rentalsDom as $rental) {
			$rentals[$i] = array();
			$rentals[$i]['id'] = $rental->find('.TitleListResultsRecordNumber input', 0)->attr['value'];
			$rentals[$i]['image_url'] = $rental->find('.TitleListResultsRecordImage img', 0)->attr['src'];
			$rentals[$i]['reference'] = $rental->find('.TitleListResultsCenterStyle4Reduced a', 0)->attr['title'];
			$rentals[$i]['title'] = $rental->find('.TitleListResultsCenterStyle4Reduced a', 0)->innertext;
			$rentals[$i]['author'] = str_replace('By ', '', $rental->find('.TitleListResultsCenterStyle4Reduced h2', 1)->innertext);
			
			//Type field is formatted like this: <img ... >Music CD 
			preg_match('/>([a-z ]*)/i', $rental->find('.TitleListResultsRecordIconStyle4', 0)->innertext, $matches);
			$rentals[$i]['type'] = $matches[1]; 
			
			//Extended data is formatted like this: <strong>title</strong>DATA<strong>title2...
			preg_match_all('/<\/strong>([£\.0-9\/]*)/', $rental->find('.TitleListResultsRightStyle4Extended', 0)->innertext, $matches);
			$rentals[$i]['due_date'] = $matches[1][0];
			$rentals[$i]['hire_fee'] = $matches[1][1];
			$rentals[$i]['overdue_fee'] = $matches[1][2];
			$rentals[$i]['sub_total'] = $matches[1][3];
			
			$i++;
		}
		
		return $rentals;
	}
	
	public function getPersonalDetails() {
		//Get page
		$dom = file_get_html($this->libraryUrl.'/01_YourAccount/01_028_YourPersonalDetails.aspx', false, $this->buildContext());
		
		//Parse details
		$details = array();
		$details['address'] = $dom->find('#ctl00_ContentPlaceCenterContent_borrowerAddressContainer', 0)->innertext;
		$details['phone_main'] = $dom->find('#ctl00_ContentPlaceCenterContent_borrowerTelephoneContainer', 0)->innertext;
		$details['phone_other'] = $dom->find('#ctl00_ContentPlaceCenterContent_borrowerSecondaryTelephoneContainer', 0)->innertext;
		$details['phone_mobile'] = $dom->find('#ctl00_ContentPlaceCenterContent_borrowerMobileContainer', 0)->innertext;
		$details['email'] = $dom->find('#ctl00_ContentPlaceCenterContent_borrowerEmailContainer', 0)->innertext;
		$details['reminder_method_available'] = $dom->find('#ctl00_ContentPlaceCenterContent_borrowerNotificationContainer', 0)->innertext;
		$details['reminder_method_overdue'] = $dom->find('#ctl00_ContentPlaceCenterContent_borrowerOverdueNotificationContainer', 0)->innertext;
		$details['marketing'] = $dom->find('#ctl00_ContentPlaceCenterContent_marketingContainer', 0)->innertext;
		$details['ethnic_origin'] = $dom->find('#ctl00_ContentPlaceCenterContent_EthnicOriginValue', 0)->innertext;
		$details['disability'] = $dom->find('#ctl00_ContentPlaceCenterContent_EmploymentValue', 0)->innertext;
		$details['language'] = $dom->find('#ctl00_ContentPlaceCenterContent_LanguageValue', 0)->innertext;
		$details['date_of_birth'] = $dom->find('#ctl00_ContentPlaceCenterContent_DOBValue', 0)->innertext;
		$details['surname'] = $this->surname;
		
		//Clean up (whitespace etc.)
		foreach ($details as $key=>$value) $details[$key] = $this->clean($value);
		
		return $details;
	}
	
	public function getExtendedItemData($isbn) {
		$dom = file_get_html($this->libraryUrl."/02_Catalogue/02_005_TitleInformation.aspx?Page=1&rcn=$isbn&fr=yl", false, $this->buildContext());
		
		//General info
		$item = array();
		$table = $dom->find('.TitleInformationTable tbody', 0);
		$item['isbn'] = 				$table->find('tr', 0)->find('td', 0)->innertext;
		$item['local_control_number'] = $table->find('tr', 1)->find('td', 0)->innertext;
		$item['title'] = 				$table->find('tr', 2)->find('td', 0)->innertext;
		$item['publication_details'] = 	$table->find('tr', 3)->find('td', 0)->innertext;
		$item['physical_description'] = $table->find('tr', 4)->find('td', 0)->innertext;
		$item['general_note'] = 		$table->find('tr', 5)->find('td', 0)->innertext;
		$item['contents_note'] = 		$table->find('tr', 6)->find('td', 0)->innertext;
		$item['publishers_number'] = 	$table->find('tr', 7)->find('td', 0)->innertext;
		$item['name_added_entry'] = 	$table->find('tr', 8)->find('td', 0)->innertext;
		
		//More complicated stuff
		preg_match('/>([a-z ]*)/i', $dom->find('#TitleInformationRecordIconStyle4', 0)->innertext, $matches);
		$item['type'] = $matches[1]; 
		preg_match('/([0-9]*) - Copies on order/i', $dom->find('#ctl00_ContentPlaceCenterContent_copyAvailabilityDisplay p', 0)->innertext, $matches);
		$item['copies_on_order'] = $matches[1];
		preg_match('/([0-9]*) copies available/i', $dom->find('#TitleInformationStyle4Row1Right', 0)->innertext, $matches);
		$item['copies_available'] = $matches[1];
		
		foreach ($item as $key=>$value) $item[$key] = $this->clean($value); //Trim etc.
		
		$item['copies'] = array();
		
		//Copy availability
		$copies = $dom->find('#ctl00_ContentPlaceCenterContent_copyAvailabilityDisplay table tbody tr');
		
		$detailHeaders = array();
		
		$i = 0;
		foreach ($copies as $copy) {
			if ($i == 0) {
				$details = $copy->find('th'); //Header row
			} else {
				$details = $copy->find('td'); //Data row
				$item['copies'][] = array();
			}
			
			$x = 0;
			foreach ($details as $detail) {
				if ($i == 0) {
					//Get header titles from header row
					$detailHeaders[] =  strtolower(str_replace(' ', '_', trim($detail->innertext)));
				} else {
					//Data row
					$item['copies'][$i-1][$detailHeaders[$x]] = trim($detail->innertext);
				}
				$x++;
			}
			$i++;
		}
		
		return $item;		
	}
	
	public function getCharges() {
		echo 'getCharges: Not implemented';
	}
	
	public function getPastLoans() {
		echo 'getPastLoans: Not implemented';
	}
	
	public function search($query) {
		echo 'search: Not implemented';
	}
	
	public function getReservations() {
		echo 'getReservations: Not implemented';
	}
	
	//MISC FUNCTIONS
	//Builds the context for web requests w/cookies & post data as needed
	private function buildContext($postData = array()) {
	
		$opts = array(
			'http'=>array(
				'method' => "GET",
				'header' => "Accept-language: en\r\n"
			)
		);
	
		//If post data provided add and change method
		if (sizeof($postData) > 0) {
			$opts['http']['content'] = http_build_query($postData);
			$opts['http']['method'] = 'POST';
		}
		
		//Add cookie strings
		$cookiesStr = '';
		foreach ($this->cookies as $name=>$value) {
			$cookiesStr .= "$name=$value; ";
		}
		
		$opts['http']['header'] = "Cookie: ".substr($cookiesStr, 0, -2)."\r\n";
		
		return stream_context_create($opts);
	}
	
	//Cleans up the given string by removing whitespace, double spaces, replaces tags with commas
	private function clean($input) {
		return trim(str_replace('  ', ' ', preg_replace('/<(.*)>/', ',', preg_replace('/\t+/', '', $input))));
	}
	
	
	//GETTERS
	public function loggedIn() {
		if (strlen($this->sessionId) > 0 && $this->loggedIn == true) {
			//TODO Check session ID works
			return true;
		}
		return false;
	}
	
	public function getSessionId() {
		return $this->cookies['ASP.NET_SessionId'];
	}
	
	
	//SETTERS
	private function setMemberDetails($membershipId, $pin, $surname) {
		$this->membershipId = $membershipId;
		$this->pin = $pin;
		$this->surname = $surname;
	}
	
	private function setCookie($name, $value) {
		$this->cookies[$name] = $value;
	}
};

?>