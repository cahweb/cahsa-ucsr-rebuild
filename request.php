<?php
/**
 * Request Details Page
 *
 * Displays a detailed output of a given request. If the user should
 * reasonably be able to edit the request, provides a button which will
 * allow them to do so.
 *
 * @category CAH
 * @package  CAHSA
 * @author   Mike W. Leavitt <michael.leavitt@ucf.edu>
 * @version  2.0.0
 */
namespace CAH\CAHSA;

require_once "preload.php";
require_once "includes/header.php";

require_once "includes/enum.request-status.php";

$currentPage = "request";

$messages = [];

// We need a request ID, or this page won't work
$reqID;
if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
    $reqID = intval($_REQUEST['id']);
} else {
    $messages[] = [
        'text' => "No request ID provided.",
        'level' => 'danger',
    ];
}

// If we're not authorized, kick them to the login page with the request ID so they can
// get sent right back
if (!$_SESSION['authorized']) {
    ?>
    <meta http-equiv="refresh" content="0;url=login.php?from=<?= $currentPage ?><?= isset($reqID) ? "id=$reqID" : "" ?>">
    <?php
} else {
    // If they're logged in already, pull up the navbar
    include "includes/navbar.php";
}

// Determine whether this person can process requests
$canProcess = $_SESSION['dept'] == 12 || $_SESSION['dept'] == 23;
$canEdit = false;

// Determine which status the user just assigned the request to.
// Used for processing pending and sent back requests.
$process = false;
$noprocess = false;
$sendBack = false;

$operation;
$reason = '';

$operationList = [
    'process'   => RequestStatus::PROCESSED,
    'noprocess' => RequestStatus::NOT_PROCESSED,
    'sendBack'  => RequestStatus::SENT_BACK,
];

// Figure out if the user clicked a button, and if so, which one
foreach ($operationList as $event => $status) {
    if (isset($_POST[$event])) {
        $$event = true;
        $operation = $status;
        break;
    }
}

// Check to make sure the reason field is filled in, if required
if (isset($operation) && $operation != RequestStatus::PROCESSED && (!isset($_POST['reason']) || empty($_POST['reason']))) {
    $messages[] = [
        'text' => "If a request is not processed, the Reason is a required field.",
        'level' => 'danger',
    ];
    $operation = null;
} elseif (isset($operation) && $operation != 'process') {
    $reason = scrubString($_POST['reason'] ?? null);
}

// Get the request data
$reqData;
$success = false;

if (!is_null($reqID)) {
    try {
        // Making all requested fields explicit just for the sake of precision and clarity
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
            $msg = "No record of request with ID $reqID.";
            $messages = [
                'text' => $msg,
                'level' => 'warning',
            ];
            throw new \Exception($msg);
        }
        $reqData = mysqli_fetch_assoc($result);
        mysqli_free_result($result);

        // If we're doing something with these results, then we'll process them
        if (isset($operation)) {
            // The `process` function is in includes/functions.php
            $success = process($operation, $reqData, $messages, $reason);
            
            // If we're successful, give the user a message to that effect
            if ($success) {
                $reqData['status'] = ucwords($operation);
                
                if (!empty($reason)) {
                    $reqData['reason'] = $reason;
                }
                
                $messages[] = [
                    'text'  => 'Request status successfully updated!',
                    'level' => 'success',
                ];
            }
        }
    } catch (\Exception $e) {
        // If we have an error, log it and send a message to the user
        logError($e);
        $messages[] = [
            'text' => 'Database error. Please try again. If this problem persists, please contact <a href="mailto:cahweb@ucf.edu">CAH Web</a>.',
            'level' => 'danger',
        ];
    } finally {
        // Close the database now that we're done
        closeDB();
    }
}

// Check to see if the user should be able to edit the request.
if (
    ($canProcess || $reqData['requestor'] == $_SESSION['userID'])  &&
    (strcasecmp($reqData['status'], RequestStatus::PENDING)   == 0 ||
     strcasecmp($reqData['status'], RequestStatus::SENT_BACK) == 0)
) {
    $canEdit = true;
}
?>

<?php if ($_SESSION['authorized']) : ?>
<main class="container mt-5 mb-4">
    <?php if (!empty($messages)) : foreach ($messages as $message) : ?>
    <div class="alert alert-<?= $message['level'] ?> alert-dismissable fade show my-2" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        <?= $message['text'] ?>
    </div>
    <?php endforeach; endif; ?>
    <div class="row">
        <h1 class="heading-underline col-sm-6 col-md-5 col-lg-4 mx-auto mb-5">View Request</h1>
    </div>
    <div class="row">
        <div class="col-12 mb-3">
        <?php if (!is_null($reqData)) : ?>
            <h2 class="h3 text-muted mb-3"><em>Request Number: <?= $reqData['id'] ?></em></h2>
            <div class="card">
                <div class="card-header">
                    <p class="h4 d-inline-block mt-2<?= !empty($reqData['reason']) ? " mb-3" : "" ?>"><strong>Status:</strong> <span class="text-complementary"><?= $reqData['status'] ?></span></p>
                <?php if ($canEdit) : ?>
                    <a href="edit.php?id=<?= $reqID ?>" class="btn btn-primary float-right">Edit</a>
                <?php endif; ?>
                <?php if (!empty($reqData['reason'])) : ?>
                    <hr>
                    <p><strong>Reason:</strong> <?= $reqData['reason'] ?></p>
                <?php endif; ?>
                </div>
                <div class="card-block">
                    <div class="row">
                        <div class="col-lg-6 mx-auto mb-3">
                            <p class="h5 text-center">Student Information</p>
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <th>Name:</th>
                                        <td><?= $reqData['name'] ?></td>
                                    </tr>
                                    <tr>
                                        <th>PID:</th>
                                        <td><?= $reqData['pid'] ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?= $reqData['email'] ?></td>
                                    </tr>
                                    <tr>
                                        <th>Year:</th>
                                        <td><?= $reqData['year'] ?></td>
                                    </tr>
                                    <tr>
                                        <th>Program:</th>
                                        <td><?= $reqData['program'] ?></td>
                                    </tr>
                                    <tr>
                                        <th>Major:</th>
                                        <td><?= $reqData['major'] ?></td>
                                    </tr>
                                    <tr>
                                        <th>Minor:</th>
                                        <td><?= $reqData['minor'] ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <hr class="mb-5">
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <p class="h5 text-center">Course Taken By Student</p>
                            <table class="table table-sm mr-3">
                                <tr>
                                    <th>Course:</th>
                                    <td><?= strtoupper("{$reqData['oprefix']} {$reqData['onumber']}") ?></td>
                                </tr>
                                <tr>
                                    <th>Description:</th>
                                    <td><?= ucfirst($reqData['otitle']) ?></td>
                                </tr>
                                <tr>
                                    <th>Units:</th>
                                    <td><?= $reqData['ohours'] ?></td>
                                </tr>
                                <tr>
                                    <th>Term:</th>
                                    <td><?= $reqData['osemester'] ?></td>
                                </tr>
                                <tr>
                                    <th>Student Grade:</th>
                                    <td><?= strtoupper($reqData['ograde']) ?></td>
                                </tr>
                                <tr>
                                    <th>Type:</th>
                                    <td><?= strtoupper($reqData['otype']) ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <p class="h5 text-center">Substituted For</p>
                            <table class="table table-sm">
                                <tr>
                                    <th>Course:</th>
                                    <td><?= strtoupper("{$reqData['uprefix']} {$reqData['unumber']} {$reqData['uother']}") ?></td>
                                </tr>
                                <tr>
                                    <th>RG:</th>
                                    <td><?= $reqData['rg'] ?></td>
                                </tr>
                                <tr>
                                    <th>RQ:</th>
                                    <td><?= $reqData['rq'] ?></td>
                                </tr>
                                <tr>
                                    <th>LN:</th>
                                    <td><?= $reqData['ln'] ?></td>
                                </tr>
                                <tr>
                                    <th>Units:</th>
                                    <td><?= $reqData['uhours'] ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <?php if ($reqData['transfer_rule'] == 1) : ?>
                        <div class="row">
                            <div class="col-lg-6 mx-auto mb-3">
                                <p class="text-center"><strong>Requests Transfer Equivalency Rule</strong></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-12 mx-auto mb-3">
                            <p class="text-center">Course Substitution should be entered in the student's <em><?= substr($reqData['uaudit'], -3) == ' - ' ? substr($reqData['uaudit'], 0, strlen($reqData['uaudit']) - 3) : $reqData['uaudit'] ?></em> audit.</p>
                        </div>
                    </div>
                <?php if (!empty($reqData['comments'])) : ?>
                    <hr>
                    <div class="row">
                        <div class="col-md-3 col-lg-2">
                            <p><strong>Comments:</strong></p>
                        </div>
                        <div class="col-md-9 col-lg-10">
                            <p><?= $reqData['comments'] ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                </div>
            <?php if ($canProcess && strcasecmp($reqData['status'], RequestStatus::PENDING) == 0) : ?>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-8 mb-3">
                            <p class="h5 font-italic mt-2 mb-3">For CAHSA Advisors only:</p>
                            <form id="processRequest" method="post">
                                <div class="form-group">
                                    <label for="reason">Reason <span class="form text text-muted">(if not processed)</span></label>
                                    <textarea id="reason" name="reason" class="form-control" rows="5"></textarea>
                                </div>
                                <button type="submit" name="process" class="btn btn-primary mr-3">Processed</button>
                                <button type="submit" name="noprocess" class="btn btn-primary mr-3">Not Processed</button>
                                <button type="submit" name="sendBack" class="btn btn-primary">Send Back</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php elseif ($canProcess && strcasecmp($reqData['status'], RequestStatus::SENT_BACK) == 0) : ?>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-8 mb-3">
                            <p>This request was sent back to the requestor for review/revision.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        <?php else : ?>
            <p class="text-center h4">No request to display.</p>
        <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-6 mx-auto mb-3">
            <p class="text-center"><a href="view.php">Return to Request List</a></p>
        </div>
    </div>
</main>
<?php endif; ?>

<?php

require_once "includes/footer.php";
