LondonLibrariesApi v1.1
==============
**by Stephen Beckett, http://twitter.com/StephenBeckett**


ABOUT
--------------

A quick & dirty API for the London Libraries website - https://www.londonlibraries.gov.uk

Very much a work in progress, please contribute:
https://github.com/stephenbeckett/LondonLibrariesApi/

There's no rate limiting built in - please use responsibly. 

Implemented api methods: login(membershipid, pin), logout(), getCurrentLoans(), getPersonalDetails(), getExtendedItemData(isbn)

To do: getCharges(), getPastLoans(), getReservations(), search(query)

To do after that: Setting functions such as extendReservation(itemId), reserveItem(itemId), then a slim based RESTful frontend

PHP 5+ required.

USAGE
--------------

	$library = new LondonLibrariesApi();

	if ($library->login(12345678901234, 1234)) {
		print_r($library->getCurrentLoans());
		print_r($library->getPersonalDetails());
	} else {
		echo $library->error();
	}
	
LICENSE
--------------

Copyright Stephen Beckett. All rights reserved.
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to
deal in the Software without restriction, including without limitation the
rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
sell copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
IN THE SOFTWARE.
