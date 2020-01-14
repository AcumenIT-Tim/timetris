<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;
    require 'vendor/autoload.php';

    $servername = "10.73.104.141";
    $username = "kromer";
    $password = "Start123!";
    $dbname = "commercial_vehicle_production";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        echo "<script type='text/javascript'>alert('Error connecting to database. Please contact I.T.');</script>";
        die("Connection failed: " . $conn->connect_error);
    }

    /*****************************************************
     *                  Variables Tracked
     * 
     ****************************************************/
    $serial_number = $_POST["serial_number"];
    $part_number   = $_POST["part_number"];
    $operator      = $_POST["operator"];
    $function_test = $_POST["function_test"];
    $cosmetic_test = $_POST["cosmetic_test"];
    $traceability  = $_POST["traceability_test"];
   

    // Checks if seat has been started. If not, displays error
    if(!startCheck($conn, $serial_number)) {
        echo "<script>alert('Error: This seat has not been started. Please contact your supervisor');</script>";
        $conn->close();
        redirect();
    }
    //Displays error if eol part number does not match start
    if(!check_part_number($conn, $serial_number, $part_number)) {
        echo "<script type='text/javascript'>alert('Part Number mismatch.')</script>";
        $conn->close();
        redirect();
    }

//  If it's a new seat, add new entry. If seat is coming from rework, update existing.
    if(!finishCheck($conn, $serial_number)) {
        if(!nokCheck($function_test, $cosmetic_test, $traceability, $serial_number)) {
            newEntry($conn, $serial_number, $part_number, $operator,"NULL",
                $function_test, $cosmetic_test, $traceability);
        }
        else {
            newEntry($conn, $serial_number, $part_number, $operator, "1",
                $function_test, $cosmetic_test, $traceability);
            setProdEnd($conn, $serial_number);
        }
    }
// Update a seat that is coming out of reowrk
    else {
        if(!dupeCheck($conn, $serial_number)) {
            echo "<script>alert('Duplicate Seat: Contact your Supervisor');</script>";
            $conn->close();
            redirect();
        }
        if(!nokCheck($function_test, $cosmetic_test, $traceability, $serial_number)) {
            updateEntry($conn, $serial_number, "NULL",
                $function_test, $cosmetic_test, $traceability);
        }
        else {
            updateEntry($conn, $serial_number, "1",
                $function_test, $cosmetic_test, $traceability);
            setProdEnd($conn, $serial_number);
        }
    }

    redirect();

/******************************************************************
 * Redirects back to the main page * 
*******************************************************************/
function redirect() {
    echo "
        <script type='text/javascript'>
                location='EOL_Form.html';
        </script>";
}

function startCheck($conn, $serial) {
    $sql = "SELECT serial_number FROM production
        WHERE serial_number='$serial';";
    $res = $conn->query($sql);

    if($res->num_rows == 0) {
        return false;
    }
    else {
        return true;
    }
}

function check_part_number($conn, $serial, $part_number) {
    $sql = "SELECT part_number FROM production WHERE serial_number='$serial'";
    $update = $conn->query($sql);
    while($row = $update->fetch_assoc()) {
        $pn = $row["part_number"];
    }
    if($part_number != $pn) { 
        return FALSE;
    } else{
        return TRUE;
    }
}

function finishCheck($conn, $serial) {
    $sql = "SELECT serial_number FROM eol
        WHERE serial_number='$serial';";
    $res = $conn->query($sql);

    if($res->num_rows == 0) {
        return false;
    }
    else {
        return true;
    }
}

function dupeCheck($conn, $serial){
    $sql = "SELECT function_test FROM eol WHERE serial_number='$serial';";
    $res = $conn->query($sql);
    $test = $res->fetch_Assoc();

    if(is_null($test["function_test"])) {
        return true;
    }
    else {
        return false;
    }
}

function newEntry($conn, $serial, $part_number, $op, $test, $fNOK, $cNOK, $tNOK) {
    $sql = "INSERT INTO eol (serial_number, part_number, operator, function_test,cosmetic_test, traceability,
        functionNOK, cosmeticNOK, traceabilityNOK) 
        VALUES ('$serial', '$part_number', '$op', $test, $test, $test, 0, 0, 0);";
   
    $conn->query($sql);

    updateEntry($conn, $serial, $test, $fNOK, $cNOK, $tNOK);

    $conn->query($sql);

}

function updateEntry($conn, $serial, $test, $fNOK, $cNOK, $tNOK) {
  

    $sql = "UPDATE eol SET
    function_test = $test,
    cosmetic_test = $test,
    traceability =  $test,
    functionNOK = functionNOK + $fNOK,
    cosmeticNOK = cosmeticNOK + $cNOK,
    traceabilityNOK = traceabilityNOK + $tNOK
    WHERE serial_number='$serial';";
    
    $conn->query($sql);
}

function setProdEnd($conn, $serial) {
    $sql ="UPDATE production SET end_time = current_timestamp() WHERE serial_number='$serial';";
    $conn->query($sql);
    
    $sql = "SELECT start_time FROM production WHERE serial_number = '$serial';";
    $res = $conn->query($sql);
    $start = $res->fetch_assoc();
    $start = $start["start_time"];


    $sql = "SELECT timestampdiff(MINUTE,'$start', current_timestamp()) AS diff";
    $res = $conn->query($sql);
    $diff = $res->fetch_assoc();
    $diff = $diff["diff"];

    $sql = "UPDATE production SET production_time = $diff WHERE serial_number = '$serial';";
    $conn->query($sql);
}


function nokCheck($func, $cos, $trace, $serial) {
    if($func or $cos or $trace) {
        nokAlert($serial);
        return false;
    }
    else{
        return true;
    }
}

function nokAlert($serial) {
    $mail = new PHPMailer(true);

try {
    //Server settings
    //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
    $mail->isSMTP();                                            // Send using SMTP
    $mail->Host       = 'smtp.office365.com';                        // Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
    $mail->Username   = 'reports@isriusa.com';                     // SMTP username
    $mail->Password   = 'Isri123!';                               // SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
    $mail->Port       = 587;                                    // TCP port to connect to

    //Recipients
    $mail->setFrom('reports@isriusa.com', 'Isri Reports');
    $mail->addAddress('q-alerts@isriusa.com');     // Add a recipient             // Name is optional
    $mail->addReplyTo('reports@isriusa.com');
    // $mail->addCC('cc@example.com');
    // $mail->addBCC('bcc@example.com');

    // Attachments
    // $mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
    // $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name

    // Content
    $msg = $serial." has failed a quality inspection.";
    $mail->isHTML(true);                                  // Set email format to HTML
    $mail->Subject = 'Commercial Vehicle Q-Alert';
    $mail->Body    = $msg;
    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

    $mail->send();
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
}
?> 