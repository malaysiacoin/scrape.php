<?php

// Include any required libraries or dependencies
require 'vendor/autoload.php';
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Chrome\ChromeOptions;

// Retrieve any data from the $_POST array
$url = $_POST['url'];
$date = $_POST['date'];

// Validate the input data
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    die("URL is not valid");
}

// Configure the WebDriver
$options = new ChromeOptions();
$options->addArguments(['--headless']);
$capabilities = DesiredCapabilities::chrome();
$capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
$driver = RemoteWebDriver::create('http://localhost:4444/wd/hub', $capabilities);

// Use the WebDriver to navigate to the specified URL
$driver->get($url);
sleep(2);

// Use the WebDriver to interact with the page and extract data
$formattedDate = date('d/m/Y', strtotime($date));
$driver->executeScript("document.getElementById('viewdate').value = '$formattedDate';");
$driver->findElement(WebDriverBy::id('viewdate'))->sendKeys("\n");
for ($i = 0; $i < 10; $i++) {
    sleep(1);
    $firstPrize = $driver->executeScript('return document.querySelector("#idrs1").textContent;');
    if ($firstPrize !== "----") {
        break;
    }
}
$data = $driver->executeScript('
    function getData() {
        var firstPrize = document.querySelector("#idrs1").textContent;
        var secondPrize = document.querySelector("#idrs2").textContent;
        var thirdPrize = document.querySelector("#idrs3").textContent;
        var specialPrizes = [];
        var specialElements = document.querySelectorAll("[class^=\'g1rs-\']:not(.g1rs-14):not(.g1rs-15):not(.g1rs-16):not(.g1rs-17):not(.g1rs-18):not(.g1rs-19):not(.g1rs-20):not(.g1rs-21):not(.g1rs-22):not(.g1rs-23)");
        for (var i = 0; i < specialElements.length; i++) {
            specialPrizes.push(specialElements[i].textContent);
        }
        var consolationPrizes = [];
        var consolationElements = document.querySelectorAll(".g1rs-14, .g1rs-15, .g1rs-16, .g1rs-17, .g1rs-18, .g1rs-19, .g1rs-20, .g1rs-21, .g1rs-22, .g1rs-23");
        for (var i = 0; i < consolationElements.length; i++) {
            consolationPrizes.push(consolationElements[i].textContent);
        }
        return {
            firstPrize: firstPrize,
            secondPrize: secondPrize,
            thirdPrize: thirdPrize,
            specialPrizes: specialPrizes,
            consolationPrizes: consolationPrizes
        };
    }
    return getData();
');

// Remove duplicate special prize numbers from the 1st, 2nd, and 3rd prizes
$data['specialPrizes'] = array_diff($data['specialPrizes'], [$data['firstPrize'], $data['secondPrize'], $data['thirdPrize']]);

// Quit the WebDriver
$driver->quit();

// Retrieve the contents of the result_template.php file
$template = file_get_contents('result_template.php');

// Replace the placeholder numbers with the scraped numbers and date
$numbers = array_merge(
    [$data["firstPrize"], $data["secondPrize"], $data["thirdPrize"]],
    $data["specialPrizes"],
    $data["consolationPrizes"]
);
foreach ($numbers as $index => $number) {
    $template = preg_replace('/<span><\/span>/', "<span>$number</span>", $template, 1);
}
$template = str_replace("{{DATE}}", $formattedDate, $template);

// Output the updated template
echo $template;
?>
