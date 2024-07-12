<?php
/**
 * Edit Request Page
 *
 * Allows a user to edit a request, or to create a new one, if they
 * arrive here from new.php. On a failed submission, remembers what
 * the user entered. If they're submitting a new request, rather than
 * editing an existing one, remembers the student's PID and other info
 * so they can create more requests for the same student.
 *
 * @category CAH
 * @package  CAHSA
 * @author   Mike W. Leavitt <michael.leavitt@ucf.edu>
 * @version  2.0.0
 */
namespace CAH\CAHSA;

require_once 'preload.php';
require_once 'includes/header.php';

// We'll use our RequestStatus pseudo-enum here, for consistency
require_once 'includes/enum.request-status.php';

$currentPage = "edit";

$messages = [];

// Get the PID, if included in `$_POST`
$pid;
if (isset($_POST['pid']) && !empty($_POST['pid'])) {
    $pid = scrubString($_POST['pid']);
}

// Get the Request ID, whether it's in `$_POST` or `$GET`
$reqID;
if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
    $reqID = intval($_REQUEST['id']);
}

// If we're not authorized, redirect to the login page
if (!$_SESSION['authorized']) {
    // If they have to login again, we'll send them back to the new request
    // page, to avoid sending PIDs via GET
    ?>
    <meta http-equiv="refresh" content="0;url=login.php?from=<?= $currentPage ?><?= isset($reqID) ? "&id=$reqID" : "" ?>">
    <?php
} else {
    // Otherwise include the navbar
    include 'includes/navbar.php';
}

// Our success flag and error flag, for user communication purposes
$success = false;
$hasError = false;
$isEdit = true;

// Container for request data, so it's in the right scope
$reqData;

// Declare student information variables so they're in the right scope
$fname;
$lname;
$email;

// Initialize course information variables so they're in the right scope
$requestor = $_SESSION['userID'];
$year = "";
$oprefix = "";
$onumber = "";
$otitle = "";
$ohours = 0;
$osemester = "";
$ograde = "";
$otype = "";
$uprefix = "";
$unumber = "";
$uother = "";
$rg = "";
$rq = "";
$ln = "";
$uhours = 0;
$transfer_rule = 0;
$comments = "";
$program = "";
$major = "";
$minor = "";
$uaudit = "";
$focus = "";
$track = "";
$status = '';

// Initializer the requestor name so we know who made the initial request
$requestorName ='';

// If we have a request ID, but we haven't submitted anything yet, we're editing
// an existing request
if (isset($reqID) && !isset($_POST['request-submit'])) {
    try {
        $sql = "SELECT r.id,
                       r.requestor,
                       r.pid,
                       r.name,
                       r.email,
                       r.year,
                       r.program,
                       r.major,
                       r.minor,
                       r.oprefix,
                       r.onumber,
                       r.otitle,
                       r.ohours,
                       r.ograde,
                       r.osemester,
                       r.otype,
                       r.uprefix,
                       r.unumber,
                       r.uother,
                       r.uhours,
                       r.rg,
                       r.rq,
                       r.ln,
                       r.uaudit,
                       r.comments,
                       r.transfer_rule,
                       s.status,
                       s.reason,
                       s.time
                FROM requests_new AS r
                    LEFT JOIN status_new AS s
                        ON r.id = s.requestid
                WHERE r.id = $reqID
        ";
        $result = mysqli_query(getDB(), $sql);
        if ($result instanceof \mysqli_result && $result->num_rows <= 0) {
            $messages[] = [
                'text'  => 'Database query for provided request ID yielded no results.',
                'level' => 'warning',
            ];
            throw new \Exception("No record of ReqID $reqID.");
        }
        
        // Store what we find as our request data
        $reqData = mysqli_fetch_assoc($result);
        mysqli_free_result($result);

        // Get requestor name for card header
        $sql = "SELECT fname, lname FROM cah.users WHERE id = $requestor LIMIT 1";
        $result = mysqli_query(getDB(), $sql);
        if ($result instanceof \mysqli_result && $result->num_rows > 0) {
            $row = mysqli_fetch_assoc($result);
            $requestorName = "{$row['fname']} {$row['lname']}";
            mysqli_free_result($result);
        }
    } catch (\Exception $e) {
        // If we have errors, log them and set the flag for later
        logError($e);
        $hasError = true;
    } finally {
        // If we have an error, close the database connection, but
        // we want to keep it open otherwise
        if ($hasError){
            closeDB();
        }
    }

    // Set all our variables, to make the form HTML look more readable and to
    // make sure everything is in the proper scope
    foreach (array_keys($reqData) as $field) {
        if ((isset($$field) && (is_numeric($reqData[$field]) || !empty($reqData[$field]))) || (!isset($$field) && 'pid' == $field)) {
            $$field = $reqData[$field];
        }
    }

    // Handle the major/minor select box and program information, which are separate here
    if (isset($uaudit) && !empty($uaudit)) {
        $pattern = '/^(Major|Minor) - (.+)?$/';
        preg_match($pattern, trim($uaudit), $matches);

        // We got the focus value. This should pretty much always exist, since it defaults to
        // "Major - "
        if (isset($matches[1])) {
            $focus = strtolower($matches[1]);
        }

        // This will grab the track value, if present
        if (isset($matches[2])) {
            $track = $matches[2];
        }
    }
} elseif (!isset($pid)) {
    // If we don't have a request ID *or* a PID, then something has gone wrong
    $messages[] = [
        'text'  => 'No Request ID or PID provided. Please create a <a href="new.php">new request</a> or <a href="view.php">see the request list</a> to edit an existing one. If you believe you are receiving this message in error, please contact <a href="mailto:cahweb@ucf.edu">CAH Web</a>.',
        'level' => 'danger',
    ];
}

// Now we populate the student data by the PID, which we should have at this point,
// whether we're doing a new request or editing an existing one
if (isset($pid)) {
    // Get the student information from the provided PID
    try {
        $sql = "SELECT PERS_PRIMARY_FIRST_NAME AS fname,
                    PERS_PRIMARY_LAST_NAME AS lname,
                    PERS_EMAIL AS email
                FROM rds.CC_PERSONAL_DIM
                WHERE PERS_EMPLID = '$pid'
                LIMIT 1
        ";
        $result = mysqli_query(getDB(), $sql);
        if ($result instanceof \mysqli_result && $result->num_rows <= 0) {
            $messages[] = [
                'text' => 'Unable to find student information in database.',
                'level' => 'danger',
            ];
        }
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);

        // Set the student info variables
        foreach (['fname', 'lname', 'email'] as $field) {
            $$field = $row[$field];
        }
    } catch (\Exception $e) {
        // If we have an error, log it and set the flag
        logError($e);
        $hasError = true;
    } finally {
        // If we're not submitting a request, then close the connection
        if (!isset($_POST['request-submit'])) {
            closeDB();
        }
    }
}

// Now we'll deal with the form submission
if (isset($_POST['request-submit']) && empty($messages)) {
    // The names of the fields, so we can loop through them
    $fields = [
        'year',
        'program',
        'oprefix',
        'onumber',
        'otitle',
        'ohours',
        'osemester',
        'ograde',
        'otype',
        'uprefix',
        'unumber',
        'uother',
        'rg',
        'rq',
        'ln',
        'uhours',
        'transfer_rule',
        'comments',
        'focus',
        'track',
    ];
    
    // Set the field variables to the ones in `$_POST`
    foreach ($fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            continue;
        }
        // Scrub the strings to prevent SQL injection
        $$field = is_string($_POST[$field]) && !is_numeric($_POST[$field])
            ? scrubString($_POST[$field])
            : $_POST[$field];
    }
    
    // Major/Minor info gets handled a bit differently
    switch($_POST['focus']) {
        case 'major':
            $major = $track;
            $uaudit = "Major - $track";
            break;
        
        case 'minor':
            $minor = $track;
            $uaudit = "Minor - $track";
            break;
    }

    // Insert the request data into the database
    try {
        // Start a transaction, so if we mess up along the way, we don't
        // end up with something half-done
        mysqli_begin_transaction(getDB(), MYSQLI_TRANS_START_READ_WRITE);

        // If we don't already have a request ID, then it's a new request
        if (!isset($reqID)) {
            $isEdit = false;
            
            // Insert into the cah_ucsr.requests_new table
            $sql = "INSERT INTO requests_new (
                                    requestor,
                                    pid,
                                    `name`,
                                    email,
                                    `year`,
                                    program,
                                    major,
                                    minor,
                                    oprefix,
                                    onumber,
                                    otitle,
                                    ohours,
                                    ograde,
                                    osemester,
                                    otype,
                                    uprefix,
                                    unumber,
                                    uother,
                                    uhours,
                                    rg,
                                    rq,
                                    ln,
                                    uaudit,
                                    comments,
                                    transfer_rule
                                )
                    VALUES      (
                                    '$requestor',
                                    '$pid',
                                    '$fname $lname',
                                    '$email',
                                    '$year',
                                    '$program',
                                    '$major',
                                    '$minor',
                                    '$oprefix',
                                    '$onumber',
                                    '$otitle',
                                    '$ohours',
                                    '$ograde',
                                    '$osemester',
                                    '$otype',
                                    '$uprefix',
                                    '$unumber',
                                    '$uother',
                                    '$uhours',
                                    '$rg',
                                    '$rq',
                                    '$ln',
                                    '$uaudit',
                                    '$comments',
                                    '$transfer_rule'
                                )
            ";

            $result = mysqli_query(getDB(), $sql);
            if (!$result) {
                throw new \Exception("Error inserting row into requests_new for PID $pid on behalf of user $requestor.");
            }

            // Get the newly-created request ID
            $reqID = mysqli_insert_id(getDB());

            // Insert into the cah_ucsr.status table
            $sql = "INSERT INTO status_new (
                                    requestid,
                                    `status`,
                                    reason,
                                    `time`
                                )
                    VALUES      (
                                    '$reqID',
                                    'Pending',
                                    '',
                                    NOW()
                                )
            ";

            $result = mysqli_query(getDB(), $sql);
            if (!$result) {
                throw new \Exception("Error inserting new row into status_new for PID $pid on behalf of user $requestor.");
            }
        // If we do have a request ID, it's an update
        } else {
            // Update the requests table
            $sql = "UPDATE requests_new
                        SET requestor     = '$requestor',
                            pid           = '$pid',
                            `name`        = '$fname $lname',
                            email         = '$email',
                            `year`        = '$year',
                            program       = '$program',
                            major         = '$major',
                            minor         = '$minor',
                            oprefix       = '$oprefix',
                            onumber       = '$onumber',
                            otitle        = '$otitle',
                            ohours        = '$ohours',
                            ograde        = '$ograde',
                            osemester     = '$osemester',
                            otype         = '$otype',
                            uprefix       = '$uprefix',
                            unumber       = '$unumber',
                            uother        = '$uother',
                            uhours        = '$uhours',
                            rg            = '$rg',
                            rq            = '$rq',
                            ln            = '$ln',
                            uaudit        = '$uaudit',
                            comments      = '$comments',
                            transfer_rule = '$transfer_rule'
                    WHERE id = $reqID
            ";
            $result = mysqli_query(getDB(), $sql);
            if (!$result) {
                throw new \Exception("Problem updating request $reqID for requestor $requestor");
            }

            // Update the status table
            $sql = "UPDATE status_new
                        SET `status` = 'Pending',
                            reason   = '',
                            `time`   = NOW()
                    WHERE requestid = $reqID
            ";
            $result = mysqli_query(getDB(), $sql);
            if (!$result) {
                throw new \Exception("Error updating status for request $reqID by requestor $requestor");
            }
        }

        // If we've gotten this far, we can commit our changes
        mysqli_commit(getDB());

        // Success!
        $success = true;
    } catch (\Exception $e) {
        // If there's an error, log it and store a message for the user
        logError($e);
        $messages[] = [
            'text' => "Problem entering request into database.",
            'level' => 'danger',
        ];

        // Then rollback the transaction. Any auto-increments will still 
        // have ticked forward, but that's not really a problem
        mysqli_rollback(getDB());
    } finally {
        // Close the connection, because we don't need it anymore
        closeDB();
    }

    // If we successfully submitted, let the user know
    if ($success) {
        $msg = '<strong>Success!</strong> See <a href="view.php">Pending Requests</a> for status.';

        // We'll use one message if they successfully edited the request, and another if it's new
        if (!$isEdit) {
            $msg .= ' To add another course substitution request for the same student, fill out the form again and click &ldquo;Submit.&rdquo; If you are finished, or wish to add a course substitution request for a different student, start a <a href="new.php">New Request</a>.';
        } else {
            $msg .= " Or you can <a href=\"request.php?id=$reqID\">view your updated request</a>.";
        }

        $messages[] = [
            'text' => $msg,
            'level' => 'success',
        ];
    }
}
?>

<?php if ($_SESSION['authorized']) : // Only show page data if they're authorized ?>
<main class="container mt-5 mb-4">
    <div class="row">
        <h1 class="heading-underline col-sm-9 col-md-8 col-lg-6 col-xl-5 mx-auto mb-5"><?= !isset($reqID) ? "New" : "Edit" ?> Request</h1>
    </div>
    <?php if (!empty($messages)) : foreach ($messages as $msg) : ?>
    <div class="alert alert-<?= $msg['level'] ?> alert-dismissable fade show mb-3" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        <p><?= $msg['text'] ?></p>
    </div>
    <?php endforeach; endif; ?>
    <?php if (isset($pid) && (!$success || !$isEdit)) : ?>
        <?php if (isset($reqID)) : ?>
        <h2 class="h3 text-muted mb-3"><em>Request Number: <?= $reqID ?></em></h2>
        <?php endif; ?>
    <div class="card">
        <div class="card-header">
            <?php if (!empty($status)) : ?>
            <div class="row mt-3">
                <p class="h4 col"><strong>Status:</strong> <span class="text-complementary"><?= $status ?></span></p>
            </div>
            <hr>
            <?php endif; ?>
            <?php if (!empty($requestorName)) : ?>
            <div class="row mb-3">
                <p class="col text-muted font-italic"><strong>Requestor:</strong> <?= $requestorName ?></p>
            </div>
            <?php endif; ?>
            <div class="row">
                <p class="col-md-4"><strong>Name: </strong><?= "$fname $lname" ?></p>
                <p class="col-md-4"><strong>Email: </strong><?= $email ?></p>
                <p class="col-md-4"><strong>PID: </strong><?= $pid ?>
            </div>
        </div>
        <div class="card-block">
            <div class="row">
                <div class="col-12">
                    <form id="request-form" method="post">
                        <input type="hidden" name="pid" value="<?= $pid ?>">
                        <div class="row">
                            <div class="form-group col-6 col-md-4">
                                <label for="year">Academic Year</label>
                                <select id="year" name="year" class="form-control">
                                    <option value=""<?= sel('', $year, $success) ?>>-- Choose One --</option>
                                    <?php foreach (['Freshman', 'Sophomore', 'Junior', 'Senior'] as $yearValue) : ?>
                                    <option value="<?= $yearValue ?>"<?= $yearValue == $year ? " selected" : "" ?>><?= $yearValue ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-lg-9 col-xl-8">
                                <label for="program">College/School</label>
                                <input type="text" id="program" name="program" class="form-control"<?= !empty($program) ? " value=\"$program\"" : "" ?> required>
                                <p class="form-text text-muted font-italic">The place the student is primarily enrolled, <em>e.g.</em>, &ldquo;College of Arts and Humanities.&rdquo;</p>
                            </div>
                        </div>
                        <p class="form-text mb-4">This substitution should be for the student's&hellip;</p>
                        <div class="row">
                            <div class="col-6 col-sm-4 col-md-3 form-group">
                                <label for="focus">Focus</label>
                                <select id="focus" name="focus" class="form-control">
                                    <?php foreach (['major', 'minor'] as $option) : ?>
                                    <option value="<?= $option ?>"<?= $option == $focus ? " selected" : "" ?>><?= ucfirst($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-7 col-md-6 form-group">
                                <label for="track">Degree Track</label>
                                <input type="text" id="track" name="track" class="form-control"<?= !empty($track) ? " value=\"$track\"" : "" ?>>
                                <p class="form-text text-muted font-italic">Name of degree track followed by type of degree (if any).</p>
                            </div>
                        </div>
                        <hr>
                        <p class="form-text my-4">Please refer to the student's PSAA interactive audit to complete the following fields as part of the exception request:</p>
                        <div class="row mb-3">
                            <div class="col-lg-6 mb-3" id="course-taken">
                                <p class="h4 text-center mb-4">Course Taken by Student</p>
                                <label class="mr-3">Course</label>
                                <div class="form-inline mb-3">
                                    <div class="form-group mr-2">
                                        <label class="sr-only" for="oprefix">Prefix</label>
                                        <input type="text" id="oprefix" name="oprefix" class="form-control mb-2" placeholder="Prefix" maxlength="3"<?= val($oprefix, $success) ?> required>
                                    </div>
                                    <div class="form-group">
                                        <label class="sr-only" for="onumber">Number</label>
                                        <input type="text" id="onumber" name="onumber" class="form-control mb-2" placeholder="Number"<?= val($onumber, $success) ?> required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="otitle">Description</label>
                                    <input type="text" id="otitle" name="otitle" class="form-control" placeholder="Course Title"<?= val($otitle, $success) ?>>
                                </div>
                                <div class="form-group">
                                    <label for="osemester">When</label>
                                    <input type="text" id="osemester" name="osemester" class="form-control"<?= val($osemester, $success) ?> required>
                                    <p class="form-text text-muted font-italic">Term and year, <em>e.g.</em>, &ldquo;Spring <?= date('Y') ?>.&rdquo;</p>
                                </div>
                                <div class="row">
                                    <div class="form-group col-6 col-md-3">
                                        <label for="ohours">Units</label>
                                        <input type="number" id="ohours" name="ohours" class="form-control" min="0" placeholder="0"<?= val($ohours, $success) ?> required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-6 col-md-3">
                                        <label for="ograde">Grade</label>
                                        <input type="text" id="ograde" name="ograde" class="form-control" maxlength="3"<?= val($ograde, $success) ?>>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="otype">Type</label>
                                    <input type="text" id="otype" name="otype" class="form-control"<?= val($otype, $success) ?>>
                                    <p class="form-text text-muted font-italic">Indicate prefix and course #, EN, TE, IP, FC, OT, or I.E.</p>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-3" id="subbed-for">
                                <p class="h4 text-center mb-4">Substituted For</p>
                                <label class="mr-3">Course</label>
                                <div class="form-inline mb-3">
                                    <div class="form-group mr-2">
                                        <label class="sr-only" for="uprefix">Prefix</label>
                                        <input type="text" id="uprefix" name="uprefix" class="form-control mb-2" maxlength="3" placeholder="Prefix"<?= val($uprefix, $success) ?>>
                                    </div>
                                    <div class="form-group">
                                        <label class="sr-only" for="unumber">Number</label>
                                        <input type="text" id="unumber" name="unumber" class="form-control mb-2" placeholder="Number"<?= val($unumber, $success) ?>>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="uother"><strong>OR</strong> Program Requirement</label>
                                    <input type="text" id="uother" name="uother" class="form-control"<?= val($uother, $success) ?>>
                                </div>
                                <div class="row mb-4">
                                    <div class="form-group col-8 col-md-4">
                                        <label for="rg">RG</label>
                                        <input type="text" id="rg" name="rg" class="form-control" maxlength="4"<?= val($rg, $success) ?>>
                                    </div>
                                    <div class="form-group col-8 col-md-4">
                                        <label for="rq">RQ</label>
                                        <input type="text" id="rq" name="rq" class="form-control" maxlength="4"<?= val($rq, $success) ?>>
                                    </div>
                                    <div class="form-group col-8 col-md-4">
                                        <label for="ln">LN</label>
                                        <input type="text" id="ln" name="ln" class="form-control" maxlength="4"<?= val($ln, $success) ?>>
                                    </div>
                                </div>
                                <div class="row mt-1">
                                    <div class="form-group col-6 col-md-3">
                                        <label for="uhours">Units</label>
                                        <input type="number" id="uhours" name="uhours" class="form-control" min="0" placeholder="0"<?= val($uhours, $success) ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="form-check mb-4">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input" name="transfer_rule" value="1"<?= chk($transfer_rule, $success) ?>>
                                Create Transfer Equivalency Rule
                            </label>
                        </div>
                        <div class="row">
                            <div class="form-group col-lg-8">
                                <label for="comments">Comments:</label>
                                <textarea class="form-control" id="comments" name="comments" rows="5"><?= !$success && !empty($comments) ? $comments : "" ?></textarea>
                            </div>
                        </div>
                        <hr>
                        <p class="form-text">Click &ldquo;Submit&rdquo; after the form is filled out. <?= isset($reqID) ? "To revert all unsubmitted changes to their previous state" : "To clear all fields" ?>, click &ldquo;Reset.&rdquo;</p>
                        <button type="submit" name="request-submit" class="btn btn-primary mr-3">Submit</button>
                        <button type="reset" name="reset" class="btn btn-primary">Reset</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>
<?php endif; ?>

<?php
require_once "includes/footer.php";
