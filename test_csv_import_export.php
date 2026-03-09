<?php
require_once 'connect.php';

// Prepare a mock CSV
$csv_content = "\xEF\xBB\xBFชื่อตำแหน่ง,ผูกสังกัด\nTest POS 1,ORG A\nTest POS 2,\nTest POS 3,ORG B\n";
file_put_contents('mock_import.csv', $csv_content);

echo "Created mock CSV.\n";

// Insert some fake orgs
$conn->exec("INSERT INTO idcard_organizations (org_name, is_active) VALUES ('ORG A', 1)");
$conn->exec("INSERT INTO idcard_organizations (org_name, is_active) VALUES ('ORG B', 1)");

echo "Done\n";
?>
