<?php
include_once('class.php');
include_once('extendclass.php');
$m  = new MyFirstClass;
$m1 = new MyExtendClass;
define('myname','Lars Errebo');
?>
<html>
<head>
<title>Dette er en Web test</title>
<link rel="stylesheet" type="text/css" href="theme.css">
</head>
<body>
<h1>Dette er titlen på siden</h1>
<?php
$a = 4;
$b = 6;
echo "Totalen er: ".($a+$b);
echo "<br/>";
$name = $_POST['name']; 
echo "Name: ".$name;
?>
<form action="index.php" method="post">
<input type="text" name="name" value="<?PHP echo $name ?>"></input>
<input type="submit">
</form>
<?php
if (isset($_POST['name'])) {
   echo $_POST['name'];
} 
?>
<table border=3>
<tr>
<th>Kol1</th>
<th>Kol2</th>
<th>Num</th>
</tr>
<tr>
<td>Test1</td>
<td>Test2</td>
<td>200000</td>
</tr>
<tr>
<td><a href="http://www.jp.dk">Jyllandsposten</a></td>
<td><a href="http://www.dr.dk">Danmarks Radio</a></td>
<td>123456</td>
</tr>
</table>
<ul>
  <li>Coffee</li>
  <li>Tea</li>
  <li>Milk</li>
</ul>
<?php
mail('lse@telemanager.dk', 'Test af mail fra PHP', 'Test af mail opsætning i PHP Apachw');
$m->method('Test');
echo "<br/>";
$m1->method();
echo "<br/>";
$m1->method2('Test2');
echo "<br/>";
echo myname;
echo "<br/>";
// arrays
$myarray = array("A","B","C","D","E","F","G");
foreach($myarray as $value)
{
    echo $value."</br>";
}
// array indhold
print_r($myarray);
// loops
for($i = 0;$i < 10;$i++) {
    echo "Stigende ".$i."</br>";

}

while($x<=5)
{
    $x++; 
    echo "The number is: $x <br>";
} 
// Global variables
echo $_SERVER['PHP_SELF'];
echo "<br>";
echo $_SERVER['SERVER_NAME'];
echo "<br>";
echo $_SERVER['HTTP_HOST'];
echo "<br>";
echo $_SERVER['HTTP_REFERER'];
echo "<br>";
echo $_SERVER['HTTP_USER_AGENT'];
echo "<br>";
echo $_SERVER['SCRIPT_NAME'];
// Exceptions
/*
throw new Exception("Dette er en undtagelse");
try{ 1 < 0; }
catch(Exception $e)
{
  echo 'Message: ' .$e->getMessage();
}*/
//date
echo "<br>";
$tomorrow = mktime(0,0,0,date("m"),date("d")+1,date("Y"));
echo "Tomorrow is ".date("Y/m/d", $tomorrow);

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', true);
ini_set('auto_detect_line_endings', true);

$inputFilename    = 'input.csv';
$outputFilename   = 'output.xml';

// Open csv to read
$inputFile  = fopen($inputFilename, 'rt');

// Get the headers of the file
$headers = fgetcsv($inputFile);

// Create a new dom document with pretty formatting
$doc  = new DomDocument();
$doc->formatOutput   = true;

// Add a root node to the document
$root = $doc->createElement('rows');
$root = $doc->appendChild($root);

// Loop through each row creating a <row> node with the correct data
while (($row = fgetcsv($inputFile)) !== FALSE)
{
  
 $container = $doc->createElement('row');

 
 foreach ($headers as $i => $header)
 {    
     $child = $doc->createElement($header);
     $child = $container->appendChild($child);
     $value = $doc->createTextNode($row[$i]);
     $value = $child->appendChild($value);
 }
 $root->appendChild($container);
   
}

$strxml = $doc->saveXML();
$handle = fopen($outputFilename, "w");
fwrite($handle, $strxml);
fclose($handle);


?>
</body>
</html>