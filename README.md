LondonLibrariesApi v1.0 by Stephen Beckett, stevebeckett.com

--- LICENSE ---
	
Copyright 2014 Stephen Beckett (http://www.stevebeckett.com, steve@stevebeckett.com)

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

	http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

--- ABOUT ---

A quick & dirty API for the London Libraries website - https://www.londonlibraries.gov.uk

Very much a work in progress, please contribute:
https://github.com/stephenbeckett/LondonLibrariesApi/

Implemented api methods: login(membershipid, pin), logout(), getCurrentLoans(), getPersonalDetails()
Still to do: getCharges(), getPastLoans(), getReservations(), search()
Eventually: Slim based frontend

PHP 5+ required.

--- USAGE ---

$library = new LondonLibrariesApi();

if ($library->login(12345678901234, 1234)) {
	print_r($library->getCurrentLoans());
	print_r($library->getPersonalDetails());
} else {
	echo $library->error();
}