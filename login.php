<?php
/**
 * Login Page
 *
 * Allows the user to login with their UCF NID credentials. Requires the LDAP
 * Apache module and the adLDAP PHP library (which I call relative to the
 * include_dir option in php.ini).
 *
 * @category CAH
 * @package  CAHSA
 * @author   Mike W. Leavitt <michael.leavitt@ucf.edu>
 * @version  2.0.0
 */
namespace CAH\CAHSA;

require_once "preload.php";
require_once "includes/header.php";

require_once "NET/adLDAP.php";
use \adLDAP;

// Try to set up the adLDAP object, and fail gracefully if it doesn't work
try {
    $adldap = new adLDAP();
} catch (\adLDAPException $e) {
    logError($e);
    $adldap = null;
}

$messages = [];
$returnTarget;
$id;

// If we were redirected from a page other than index.php, get that query
// parameter and store it for later
if (isset($_GET['from']) && !empty($_GET['from'])) {
    $returnTarget = $_GET['from'];

    // Also store the ID parameter, if it's included (used when coming
    // from request.php and edit.php)
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $id = intval($_GET['id']);
    }
}

// User has submitted the login form
if (isset($_POST['submit'])) {
    if (!isset($_POST['nid']) || empty($_POST['nid'])) {
        // No NID
        $messages[] = [
            'text' => 'You must enter your NID.',
            'level' => 'warning',
        ];
    } elseif (!isset($_POST['pass']) || empty($_POST['pass'])) {
        // No Password
        $messages[] = [
            'text' => 'No password provided.',
            'level' => 'warning',
        ];
    }

    // Scrub the NID, to avoid SQL injection, and declare the user ID
    $nid = scrubString($_POST['nid']);
    $userID;

    // If we haven't had errors yet, try to login
    if (empty($messages) &&
        !is_null($adldap) &&
        $adldap->authenticate($nid, $_POST['pass'])
    ) {
        // Check whether the user is in our database and, if so, whether they're
        // authorized to be an advisor
        try {
            // Initialize HTML output
            $outHTML = '';

            // Check to see if they're in our cah.users table
            $sql = "SELECT id FROM cah.users WHERE nid LIKE '$nid' LIMIT 1";
            $result = mysqli_query(getDB(), $sql);
            if ($result instanceof \mysqli_result && $result->num_rows <= 0) {
                $messages[] = [
                    'text' => 'Provided NID is not registered with the CAH systems. If you require access to this application, please contact <a href="mailto:cahweb@ucf.edu">CAH Web</a>.',
                    'level' => 'danger',
                ];
                throw new \Exception("No results found in users table for NID $nid!");
            }

            // Store the ID, if we have it
            $row = mysqli_fetch_assoc($result);
            $userID = intval($row['id']);

            // Check to see if they're authorized as an advisor in the cah_ucsr.advisors table
            $sql = "SELECT user_id, department_id FROM advisors WHERE user_id = $userID LIMIT 1";
            $result = mysqli_query(getDB(), $sql);
            if ($result instanceof \mysqli_result && $result->num_rows <= 0) {
                $messages[] = [
                    'text' => 'User account not authorized for this application. If you require access to this application, please contact <a href="mailto:cahweb@ucf.edu">CAH Web</a>.',
                    'level' => 'danger',
                ];
                throw new \Exception("User $userID is not in list of authorized advisors!");
            }

            // Store the fetched data for later
            $row = mysqli_fetch_assoc($result);

            // Set the session variables, at this point
            $_SESSION['userID'] = $userID;
            $_SESSION['dept'] = intval($row['department_id']);
            $_SESSION['authorized'] = true;
            $_SESSION['loggedInAt'] = time();

            // Start an output buffer for the HTML
            ob_start();
            ?>
            <main class="container mb-3">
                <div class="alert alert-success">
                    <strong>Success!</strong> Your login information has been verified!
                </div>
                <meta http-equiv="refresh" content="0;url=<?= $returnTarget ? $returnTarget : 'index' ?>.php<?= isset($id) ? "?id=$id" : "" ?>">
            </main>
            <?php
            // Store the buffered output
            $outHTML = ob_get_clean();
        } catch (\Exception $e) {
            // If we experience an error along the way, the user isn't authorized, and we should report it
            $_SESSION['authorized'] = false;
            logError($e);
        } finally {
            // If we have an open database connection, close it
            closeDB();
            if (!empty($outHTML)) {
                echo $outHTML;
            }
        }
    }
}
?>
<div class="container">
    <h1 class="text-center font-condensed text-primary-aw text-uppercase">Undergraduate Course Subsititution Request</h1>
</div>
<main class="container mt-5 mb-4">
    <?php if (!empty($messages)) : foreach ($messages as $msg) : ?>
    <div class="alert alert-<?= $msg['level'] ?> alert-dismissable fade show mx-2" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        <?= $msg['text'] ?>
    </div>
    <?php endforeach; endif; ?>
    <div class="row">
        <div class="col-md-4 mx-auto">
            <div class="card bg-primary rounded mt-4">
                <div class="card-block">
                    <h2 class="card-title">Login</h2>
                    <form id="loginForm" method="post">
                        <div class="form-group">
                            <label for="nid">NID</label>
                            <input type="text" class="form-control" id="nid" name="nid" placeholder="ab123456" value="<?= isset($_POST['nid']) ? $_POST['nid'] : '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="pass">Password</label>
                            <input type="password" class="form-control" id="pass" name="pass" placeholder="••••••••••••••••••">
                        </div>
                        <button type="submit" name="submit" class="btn btn-secondary">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
