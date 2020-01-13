<?php
    $servername = "10.73.104.141";
    $username = "production";
    $password = "Isri123!";
    $dbname = "commercial_vehicle_production";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        echo "<script type='text/javascript'>alert(Call Tim;</script>";
        die("Connection failed: " . $conn->connect_error);
    }
    
    $serial_number = $_POST["serial_number"];
    $order_number  = $_POST["order_number"];
    $struct_serial = $_POST["struct_serial_number"];
    $part_number   = $_POST["part_number"];
    $operator      = $_POST["operator"];

    $sql = "INSERT INTO production (serial_number, operator, part_number, order_number, struct_serial_number)
        VALUES('$serial_number', '$operator', '$part_number', '$order_number', '$struct_serial')";

    $result = $conn->query($sql);

    if ($result) {
        $conn->close();
        redirect();

        
    } else {
         switch(mysqli_errno($conn)) {
                case 1366:
                    echo "<script type='text/javascript'>alert('Invalid Serial Number or Order Number.
                        Make sure that only numbers are entered.');</script><br>".$conn->error;
                    break;
                case 1062:
                    echo "<script type='text/javascript'>alert('Duplicate Serial Number. Contact your Supervisor.');</script>";
                    break;
                default:
                    echo "Error: <br>".$conn->error."<br>".mysqli_errno($conn);
                    break;
            }
    
            redirect();
        
    }

/******************************************************************
 * Redirects back to the main page * 
*******************************************************************/
function redirect() {
    echo "
        <script type='text/javascript'>
                location='NTS_production_tracker.html';
        </script>";
}
?> 