<?php
/**
 * View Request List Page
 *
 * Displays a list of requests, separated visually by tabs. Sent Back
 * requests are shown with Pending requests, for ease of reference.
 *
 * @category CAH
 * @package  CAHSA
 * @author   Mike W. Leavitt <michael.leavitt@ucf.edu>
 * @version  2.0.0
 */
namespace CAH\CAHSA;

require_once "preload.php";
require_once "includes/header.php";

// We'll use our Request Status pseudo-enum, for the sake of consistency
require_once "includes/enum.request-status.php";

$currentPage = 'view';

// If we're not authorized, redirect to the login page
if (!$_SESSION['authorized']) {
    ?>
    <meta http-equiv="refresh" content="0;url=login.php?from=<?= $currentPage ?>">
    <?php
} else {
    // Otherwise load the navigation bar
    include "includes/navbar.php";
}

// Set what type of request to default to
$type = RequestStatus::PENDING;
if (isset($_REQUEST['type']) && !empty($_REQUEST['type'])) {
    $type = html_entity_decode($_GET['type']);
}

// Declare and initialize stuff we'll need in global scope
$messages = [];
$hasResults = false;
$result;

try{
    // Get the requests of the given type
    $typeStr = $type == RequestStatus::PENDING ? "$type' OR `status` LIKE 'sent back" : $type;
    $sql = "SELECT DISTINCT requestid,
                            `name`,
                            uaudit,
                            major,
                            `status`,
                            `time`,
                            requestor,
                            users.fname,
                            users.mname,
                            users.lname
             FROM           requests_new
                LEFT JOIN advisors
                    ON requests_new.requestor = advisors.user_id
                LEFT JOIN status_new
                    ON status_new.requestid = requests_new.id
                LEFT JOIN cah.users
                    ON users.id = advisors.user_id
             WHERE `status` LIKE '$typeStr'
    ";

    // If the user isn't from CAHSA or from IT, then we want to only pull requests
    // for their specific department
    if ($_SESSION['dept'] != 12 && $_SESSION['dept'] != 23) {
        $programs = [];

        // Get the list of the available department programs
        $deptSql = "SELECT `description` FROM cah.departments_programs WHERE department_id = {$_SESSION['dept']}";

        $result = mysqli_query(getDB(), $deptSql);
        if ($result instanceof \mysqli_result && $result->num_rows <= 0) {
            $messages[] = [
                'text' => "Unable to list deptartments for department ID {$_SESSION['dept']}.",
                'level' => 'danger',
            ];
            throw new \Exception("Unable to retrieve department list for department {$_SESSION['dept']}!");
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $programs[] = trim(strtok($row['description'], '-'));
        }
        mysqli_free_result($result);
        unset($deptSql);

        // Add them to the query
        $program_list = implode("', '", $programs);
        $sql .= " AND (REPLACE(major, SUBSTRING_INDEX(major, ' ', -1), '') IN ('$program_list') OR REPLACE(minor, SUBSTRING_INDEX(minor, ' ', -1), '') IN ('$program_list') OR major = '')";
    }

    // Filter by PID
    if (isset($_POST['pid']) && !empty($_POST['pid'])) {
        $pid = scrubString($_POST['pid']);
        $sql .= " AND requests_new.pid = '$pid'";
    }

    // We want oldest to newest, though we could easily change that if they needed us to
    $sql .= " GROUP BY requests_new.id ORDER BY time ASC";

    $result = mysqli_query(getDB(), $sql);
    if ($result instanceof \mysqli_result && $result->num_rows > 0) {
        $hasResults = true;
    }
} catch (\Exception $e) {
    // If we have an error, log it and close the database connection
    logError($e);
    closeDB();
}

$requestTypes = ['pending', 'processed', 'not processed'];
?>

<?php if ($_SESSION['authorized']) : ?>
<main class="container mt-5 mb-4">
    <div class="row">
        <h1 class="heading-underline col-sm-9 col-md-8 col-lg-6 col-xl-5 mx-auto mb-5">Request List</h1>
    </div>
    <div class="row">
        <div class="col-md-8 col-lg-6 col-xl-5">
            <form id="pid-filter" method="post" class="form-inline mb-3">
                <div class="form-group mr-2 mb-2">
                    <label for="pid">Filter PID:</label>
                    <input type="text" class="form-control mx-2" id="pid" name="pid" placeholder="1234567" maxlength="7">
                </div>
                <button type="submit" class="btn btn-primary mb-2">Filter</button>
            </form>
        </div>
    </div>
    <div class="row">
        <div class="col-12 mx-auto">
            <ul class="nav nav-tabs">
                <?php foreach ($requestTypes as $requestType) : ?>
                <li class="nav-item">
                    <a class="nav-link<?= $requestType == $type ? ' active' : '' ?>" href="view.php?type=<?= htmlentities($requestType) ?>"><?= str_replace(' ', '&nbsp;', ucwords($requestType)); ?></a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-12 w-100">
            <table id="requestsTable" class="table table-bordered table-striped table-hover<?= $hasResults ? " table-responsive" : "" ?>">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Audit</th>
                        <th>Major</th>
                        <th>Requestor</th>
                        <th>Status</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($hasResults) : while ($row = mysqli_fetch_assoc($result)) : ?>
                    <tr>
                        <td class="text-center"><a href="request.php?id=<?= $row['requestid'] ?>"><?= $row['requestid'] ?></a></td>
                        <td><?= $row['name'] ?></td>
                        <td><?= $row['uaudit'] ?></td>
                        <td><?= $row['major'] ?></td>
                        <td><?= $row['fname'] . (!empty($row['mname']) ? " {$row['mname']}" : "") . " {$row['lname']}" ?></td>
                        <td><?= str_replace(" ", "&nbsp;", $row['status']) ?></td>
                        <td><?= str_replace(" ", "&nbsp;", date('m/d/y g:ia', strtotime($row['time']))) ?></td>
                    </tr>
                    <?php endwhile; else : ?>
                    <tr>
                        <td class="text-center" colspan="7"><p class="mt-3">No results to display.</p></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php endif; ?>

<?php
closeDB();

require_once "includes/footer.php";
