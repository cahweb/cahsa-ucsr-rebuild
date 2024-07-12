<?php
namespace CAH\CAHSA;

// Load PHPMailer
require_once "vendor/autoload.php";
use \PHPMailer\PHPMailer\PHPMailer;
use \PHPMailer\PHPMailer\Exception;

// The constant-filled class we're using as an pseudo-enum
require_once "enum.request-status.php";

// The current page we're on. Used for login redirection.
$currentPage = "";

// Set mysqli to pass SQL errors to PHP
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// We want PHP to keep track of this in global scope, for ease of
// reference later on
global $dbConnection;

/**
 * Gets or creates a database connection
 *
 * @return mysqli|null
 */
function getDB() : ?\mysqli
{
    global $dbConnection;

    // Get our environment variables loaded from our .env file
    $host    = getenv('DB_HOST');
    $user    = getenv('DB_USER');
    $pass    = getenv('DB_PASS');
    $db      = getenv('DB');
    $charset = getenv('DB_CHARSET');

    // Create the connection if we don't have one yet
    if (is_null($dbConnection)) {
        try {
            $dbConnection = mysqli_connect($host, $user, $pass, $db);
            mysqli_set_charset($dbConnection, $charset);
        } catch (\mysqli_sql_exception $e) {
            // At this point in the life cycle, this will only be a
            // connection error
            logError($e);
            $dbConnection = null;
        }
    }

    // Return the connection
    return $dbConnection;
}


/**
 * Closes the database connection, if present
 *
 * @return void
 */
function closeDB()
{
    global $dbConnection;
    
    // If we have a connection, close it and null the variable
    if (!is_null($dbConnection) && $dbConnection instanceof \mysqli) {
        mysqli_close($dbConnection);
        $dbConnection = null;
    }
}


/**
 * Prepares a string for entry into an SQL query
 *
 * Performs standard preparation: escaping special characters, encoding
 * HTML entities, etc., all with a view toward preventing SQL injection.
 * If the input string is `null`, acts as a pass-through.
 *
 * @param string|null $str  The string to be prepped
 *
 * @return string|null
 */
function scrubString(string|null $str) : ?string
{
    if (is_null($str)) {
        return $str;
    }
    return mysqli_real_escape_string(getDB(), htmlentities($str));
}


/**
 * Start a session, if one does not already exist
 *
 * This is safer than just calling `session_start`, since it avoids
 * the risk of trying to start one unnecessarily, or if server sessions
 * have been disabled. Returns a boolean to signify whether a session
 * was started or not.
 *
 * @return bool
 */
function maybeStartSession() : bool
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
        return true;
    }
    return false;
}


/**
 * End a session, if one exists
 *
 * The opposite number to `maybeStartSession`: destroys the existing
 * session, if there is one active. Only used for logging users out.
 * Returns a boolean value to signify whether the session was destroyed
 * or not.
 *
 * @return bool
 */
function maybeEndSession() : bool
{
    if (session_status() == PHP_SESSION_ACTIVE) {
        session_destroy();
        return true;
    }
    return false;
}


/**
 * Writes a message to the PHP error log
 *
 * Used for more graceful or informative error handling in other code. Can
 * handle any instance of the PHP standard `Exception` class (or any class
 * which inherits from it) *or* can just take a string.
 *
 * @param Exception|string $e  The item to be logged
 *
 * @return void
 */
function logError(\Exception|string $e)
{
    // If we're dealing with an Exception, get the message
    $msg = '';
    if (!is_string($e)) {
        $msg = $e->__toString();
    } else {
        $msg = $e;
    }

    // Add a newline, for cleanliness
    $msg .= "\n";

    // Log the error
    error_log($msg);
}


/**
 * Process an update to the status of a substitution request
 *
 * Updates everything in the database and sends emails to those concerned.
 * Returns true if everything is successful, and false if not.
 *
 * @param string $requestStatus  Technically a string, but should be a constant
 *                                  value in the \CAH\CAHSA\RequestStatus class
 * @param array $reqData  The data relevant to the request
 * @param array &$messages  The messages array, for user communication
 * @param string $reason  The reason for a status update, if not Processed
 *
 * @return bool
 */
function process(
    string $requestStatus,
    array  $reqData,
    array  &$messages,
    string $reason = ''
) : bool
{
    // Update the status in the database
    $status = ucwords($requestStatus);

    $sql = "UPDATE status_new SET `status` = '$status', reason = '$reason' WHERE requestid = {$reqData['id']}";

    $result = mysqli_query(getDB(), $sql);
    if (!$result) {
        $messages[] = [
            'text'  => 'Error updating request status in database.',
            'level' => 'danger',
        ];
        return false;
    }

    /*
     * Send emails to the appropriate parties. If a request is processed,
     * the email is sent to the student. If it's not processed or sent
     * back, the email is sent to the requestor.
     */
    $subject = "Course Substitution Request Update";
    $from = [
        'addr' => "cahsa@ucf.edu",
        'name' => "College of Arts and Humanities Student Advising",
    ];
    $body = '';

    // Declaring these in case we need them
    $cc;
    $bcc;

    if ($requestStatus == RequestStatus::PROCESSED) {
        // Add the student as the recipient
        $to = [
            'addr' => html_entity_decode($reqData['email']),
            'name' => html_entity_decode($reqData['name']),
        ];
        
        //We want to blind copy CAHSA, so they have a record of it
        $bcc = $from;

        // We'll use the student email text
        ob_start();
        include_once "emails/student.php";
        $body = ob_get_clean();
    } else {
        // Get the requestor's name and email, so we can notify them
        $sql = "SELECT fname, lname, email FROM cah.users WHERE id = {$reqData['requestor']}";
        $result = mysqli_query(getDB(), $sql);

        if ($result instanceof \mysqli_result && $result->num_rows <= 0) {
            $messages[] = [
                'text'  => 'Could not find Requestor information in database.',
                'level' => 'danger',
            ];
            return false;
        }

        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);

        // Cache the requestor name, because we'll need to get the current user's name, too
        $requestorName = $row['fname'];

        $to = [
            'addr' => $row['email'],
            'name' => "{$row['fname']} {$row['lname']}",
        ];

        // Use our advisor email, as opposed to the student email
        ob_start();
        include_once "emails/advisor.php";
        $body = ob_get_clean();

        // Add the status to the previously-established subject line
        $subject .= ": $status";
    }

    // Try to send the email
    $mailSuccess = sendEmail($to, $from, $subject, $body, $cc ?? null, $bcc ?? null);

    // Tell the user in the email failed but the DB operation succeeded
    if (!$mailSuccess) {
        $messages[] = [
            'text'  => 'The status was updated, but the required email could not be sent. Please contact <a href="mailto:cahweb@ucf.edu">CAH Web</a> to resolve this issue.',
            'level' => 'danger',
        ];
    }

    // Return our success state
    return $mailSuccess;
}


/**
 * Send an email via PHPMailer
 *
 * Syntactic sugar for the standard PHPMailer boilerplate. For addresses,
 * accepts both strings and arrays (and, for `$to`, `$cc`, and `$bcc`,
 * arrays of arrays). Assumes SMTP authorization via `$_ENV` and HTML email
 * body.
 *
 * @param string|array $to  The recipient(s)
 * @param string|array $from  The sender
 * @param string $subject  Subject line
 * @param string $body  Email body. This function assumes HTML content
 * @param string|array $cc  Any addresses for the CC field
 * @param string|array $bcc  Any addresses for the BCC field
 *
 * @return bool
 */
function sendEmail(
    string|array $to,
    string|array $from,
    string       $subject,
    string       $body,
    string|array $cc  = null,
    string|array $bcc = null
) : bool
{
    // Create the PHPMailer object and set the options
    $mail = new PHPMailer();
    $mail->IsSMTP();
    $mail->IsHTML(true);
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = false;
    $mail->Host       = getenv('EMAIL_HOST');
    $mail->Port       = getenv('EMAIL_PORT');
    $mail->Username   = getenv('EMAIL_USER');
    $mail->Password   = getenv('EMAIL_PASS');
    $mail->CharSet    = 'utf-8';

    $mail->Subject    = $subject;
    $mail->Body       = $body;

    // Add main recipients
    if (!is_array($to)) {
        $mail->AddAddress($to);
    } elseif (isset($to['addr'])) {
        $mail->AddAddress($to['addr'], $to['name']);
    } else {
        foreach ($to as $addr) {
            $mail->AddAddress($addr['addr'], $addr['name']);
        }
    }

    // Add whomever the email is from
    if (!is_array($from)) {
        $mail->SetFrom($from);
    } else {
        $mail->SetFrom($from['addr'], $from['name']);
    }

    // CC and BCC. This isn't very DRY, but I can't think of a
    // cleverer way to do it
    if (isset($cc)) {
        if (!is_array($cc)) {
            $mail->AddCC($cc);
        } elseif (isset($cc['addr'])) {
            $mail->AddCC($cc['addr'], $cc['name']);
        } else {
            foreach ($cc as $addr) {
                $mail->AddCC($addr['addr'], $addr['name']);
            }
        }
    }

    if (isset($bcc)) {
        if (!is_array($bcc)) {
            $mail->AddBCC($bcc);
        } elseif (isset($bcc['addr'])) {
            $mail->AddBCC($bcc['addr'], $bcc['name']);
        } else {
            foreach ($bcc as $addr) {
                $mail->AddBCC($addr['addr'], $addr['name']);
            }
        }
    }

    // Try to send the email, report any errors, and return the result
    $success = false;

    try {
        $success = $mail->send();
    } catch (Exception $e) {
        logError($e);
        logError("PHPMailer Error: " . $mail->ErrorInfo);
    } catch (\Exception $e) {
        logError($e);
    }

    return $success;
}


/**
 * Returns a string to set the value of an HTML text input element
 *
 * Checks to see if there is a value to add, based on the source variable
 * and whether the form submission was successful. Enables form entries to
 * persist on a failed submission. Syntactic sugar to make it shorter and
 * more readable as the code builds the form. Similar to `sel` and `chk`.
 *
 * @param int|string $datum  The value to be checked/interpolated
 * @param bool $success  Whether the form submission was successful
 *
 * @return string
 */
function val(int|string $datum, bool $success) : string
{
    // If we weren't successful and the value isn't empty, set the
    // input value to whatever it was.
    return !$success && !empty($datum) ? " value=\"$datum\"" : "";
}


/**
 * Returns a string to set the value of an HTML option element
 *
 * Checks to see if there is a value to add, based on the source variable
 * and whether the form submission was successful. If there is, checks
 * against the current value of the select option that is being generated,
 * and sets it to be the option selected. Enables form entries to persist
 * on a failed submission. Syntactic sugar to make it shorter and more
 * readable as the code builds the form. Similar to `val` and `chk`.
 *
 * @param int|string $value  The value to be checked/interpolated
 * @param int|string $datum  The value entered in the previous form submission
 * @param bool $success  Whether the form submission was successful
 *
 * @return string
 */
function sel(int|string $value, int|string $datum, bool $success) : string
{
    // If we weren't successful and the value isn't empty, set the
    // option to be selected.
    return !$success && $datum == $value ? " selected" : "";
}


/**
 * Returns a string to set the value of an HTML checkbox element
 *
 * Checks to see if there is a value to add, based on the source variable
 * and whether the form submission was successful. Enables form entries to
 * persist on a failed submission. Syntactic sugar to make it shorter and
 * more readable as the code builds the form. Similar to `val` and `sel`.
 *
 * @param int|string $value  The value to be checked/interpolated
 * @param bool $success  Whether the form submission was successful
 *
 * @return string
 */
function chk(int|string $value, bool $success)
{
    return !$success && $value && !empty($value) ? " checked" : "";
}
