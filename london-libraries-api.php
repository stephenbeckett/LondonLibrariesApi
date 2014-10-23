<?php

require_once('london-libraries-exceptions.php');
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
	
	private function buildContext($postData = array()) {
	
		$opts = array(
			'http'=>array(
				'method' => "GET",
				'header' => "Accept-language: en\r\n"
			)
		);
	
		if (sizeof($postData) > 0) {
			$opts['http']['content'] = http_build_query($postData); //Strip off trailing &
			$opts['http']['method'] = 'POST';
		}
	
		$cookiesStr = '';
		foreach ($this->cookies as $name=>$value) {
			$cookiesStr .= "$name=$value; ";
		}
		
		$opts['http']['header'] = "Cookie: ".substr($cookiesStr, 0, -2)."\r\n";
		
		//print_r($opts);
		
		return stream_context_create($opts);
	}
	
	//API METHODS
	//	Auth
	public function login($membershipId, $pin) {
		//Attempt to log in
		
		//Call once to get viewstate and eventvalidation
		$html = file_get_contents($this->libraryUrl.'/00_002_login.aspx');
		$this->setCookie('ASP.NET_SessionId', substr($http_response_header[6], 30, 24)); //Not v robust, could iterate responses + regex
		$dom = str_get_html($html);
		
		$AspViewState = $dom->find('#__VIEWSTATE', 0)->attr['value'];
		$AspEventValidation = $dom->find('#__EVENTVALIDATION', 0)->attr['value'];
		
		//Submit with password & username
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
			//Logged in
			preg_match('/=([a-z0-9]*);/i', $http_response_header[7], $matches);
			$this->setCookie('viewpoint', $matches[1]);
			$surname = $dom->find('.borrowername', 0)->innertext;
			$this->setMemberDetails($membershipId, $pin, $surname);
			$this->loggedIn = true;
			return true;
		} else {
			$this->error = $dom->find('#ctl00_ContentPlaceCenterContent_login2 .error', 0)->innertext;
			$this->loggedIn = false;
			return false;
		}
	}
	
	public function logout() {
		//Sign out from site
		//Clear session id
		$this->loggedIn = false;
	}
	
	public function getCurrentLoans() {
		$dom = file_get_html($this->libraryUrl.'/01_YourAccount/01_002_YourLoans.aspx', false, $this->buildContext());
		
		$rentalsDom = $dom->find('.TitleListResultsItemContainerStyle4');
		$rentals = array();
		$i = 0;
		
		foreach ($rentalsDom as $rental) {
			$rentals[$i] = array();
			$rentals[$i]['record_number'] = $rental->find('.TitleListResultsRecordNumber input', 0)->attr['value'];
			$rentals[$i]['image_url'] = $rental->find('.TitleListResultsRecordImage img', 0)->attr['src'];
			$rentals[$i]['reference'] = $rental->find('.TitleListResultsCenterStyle4Reduced a', 0)->attr['title'];
			$rentals[$i]['title'] = $rental->find('.TitleListResultsCenterStyle4Reduced a', 0)->innertext;
			$rentals[$i]['author'] = str_replace('By ', '', $rental->find('.TitleListResultsCenterStyle4Reduced h2', 1)->innertext);
			
			preg_match('/>([a-z ]*)/i', $rental->find('.TitleListResultsRecordIconStyle4', 0)->innertext, $matches);
			$rentals[$i]['type'] = $matches[1];
			
			//print_r($rental->find('.TitleListResultsRightStyle4Extended', 0)->innertext);
			
			preg_match_all('/<\/strong>([£\.0-9\/]*)/', $rental->find('.TitleListResultsRightStyle4Extended', 0)->innertext, $matches);
			//print_r($matches);
			$rentals[$i]['due_date'] = $matches[1][0];
			$rentals[$i]['hire_fee'] = $matches[1][1];
			$rentals[$i]['overdue_fee'] = $matches[1][2];
			$rentals[$i]['sub_total'] = $matches[1][3];
			
			$i++;
		}
		
		return $rentals;
	}
	
	public function getReservations() {
		
	}
	
	public function getPersonalDetails() {
		$dom = file_get_html($this->libraryUrl.'/01_YourAccount/01_028_YourPersonalDetails.aspx', false, $this->buildContext());
		
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
		
		foreach ($details as $key=>$value) $details[$key] = $this->clean($value);
		
		//echo $dom->save();
		
		return $details;
	}
	
	private function clean($input) {
		return trim(str_replace('  ', ' ', preg_replace('/<(.*)>/', ',', preg_replace('/\t+/', '', $input))));
	}
	
	public function getCharges() {
		
	}
	
	public function getPastLoans() {
		
	}
	
	public function search() {
		
	}
	
	
	//GETTERS
	public function loggedIn() {
		if (strlen($this->sessionId) > 0 && $this->loggedIn == true) {
			//Check session ID works
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
		//echo "$name = $value";
		$this->cookies[$name] = $value;
	}
};

?>