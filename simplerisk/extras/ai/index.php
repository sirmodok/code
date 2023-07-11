<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2022 to SimpleRisk, Inc.    *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to       *
 * automatically upgrade the application and database.              *
 ********************************************************************/

// Extra Version
define('UPGRADE_EXTRA_VERSION', '20230331-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../../includes/config.php'));
require_once(realpath(__DIR__ . '/../../includes/services.php'));
require_once(realpath(__DIR__ . '/backwards_compatibility.php'));

// For backwards compatibility only include the extras.php file if it exists
if (file_exists(realpath(__DIR__ . '/../../includes/extras.php')))
{
	require_once(realpath(__DIR__ . '/../../includes/extras.php'));
}

// For backwards compatibility only include the autoload.php file if it exists
if (file_exists(realpath(__DIR__ . '/../../vendor/autoload.php')))
{
	require_once(realpath(__DIR__ . '/../../vendor/autoload.php'));
}

// If the current version of the SimpleRisk app is less than 20230106-001
if (current_version("app") < '20230106-001')
{
    // Include Zend Escaper for HTML Output Encoding
    // Ignoring the detection here as this is for backward compatibility
    // @phan-suppress-next-line PhanUndeclaredClassMethod
    $escaper = new Zend\Escaper\Escaper('utf-8');
}
// Otherwise if we have a version 20230106-001 or newer
else
{
    // Include Laminas Escaper for HTML Output Encoding
    $escaper = new simpleriskEscaper();
}

// Add various security headers
add_security_headers();

if (!isset($_SESSION))
{
    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start the session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

    session_name('SimpleRisk');
    session_start();
}

// Include the language file
// Ignoring detections related to language files
// @phan-suppress-next-line SecurityCheck-PathTraversal
require_once(language_file());

// If a POST was submitted
if (isset($_POST['backup']) || isset($_POST['app_upgrade']) || isset($_POST['db_upgrade']))
{
    // Don't use CSRF Magic for backup
    if (!isset($_POST['backup']))
    {
        csrf_init();
    }

    // Check for session timeout or renegotiation
    session_check();

    // Check if access is authorized
    if (!isset($_SESSION["access"]) || ($_SESSION["access"] != "1" && $_SESSION["access"] != "granted"))
    {
        exit(0);
    }

    // Check if access is authorized
    if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
    {
        exit(0);
    }

    // If the user posted to backup the database
    if (isset($_POST['backup']))
    {
        // Backup the database
        if(!backup_database()){
            header("Location: ".$_SERVER['HTTP_REFERER']);
            exit(0);
        }
    }
    else if (isset($_POST['app_upgrade']))
    {
        // Upgrade the SimpleRisk application
        upgrade_application();
    }
    else if (isset($_POST['db_upgrade']))
    {
        // Upgrade the SimpleRisk database
        upgrade_database();
    }

}

/************************************************************************
 * FUNCTION: DO PRE-UPGRADE CHECK                                       *
 * The function is supposed to contain a set of pre-upgrade             *
 * checks to look for possible issues that could make the upgrade fail  *
 ************************************************************************/
function do_pre_upgrade_check() {
    $issues = [];
    
    if (!(PHP_VERSION_ID >= 80100)) {
        $issues[] = 'The application requires a PHP version ">= 8.1.0". You are running ' . PHP_VERSION . '.';
    }
    
    if (!empty($issues)) {
        json_response(400, implode(', ', $issues), null);
    }
}

/**********************************
 * FUNCTION: NEW DISPLAY UPGRADES *
 **********************************/
function new_display_upgrades()
{
    global $escaper;
    global $lang;

    echo $escaper->escapeHtml($lang['UpgradeInstructions']);
    echo "<br /><br />\n";

    echo "<form name=\"upgrade_simplerisk\" method=\"post\" action=\"".$_SESSION['base_url']."/extras/upgrade/index.php\" target=\"_blank\" style='margin-bottom: 0px'>\n";
    echo "<input type=\"submit\" name=\"backup\" id=\"backup\" value=\"" . $escaper->escapeHtml($lang['BackupDatabaseButton']) . "\" />\n";

    // Get the hosting tier
    $hosting_tier = get_setting("hosting_tier");

    // If the hosting tier is not set
    if ($hosting_tier == false)
    {
        // Display the Upgrade SimpleRisk button
        echo "<input type=\"button\" name=\"app_upgrade\" id=\"app_upgrade\" value=\"" . $escaper->escapeHtml($lang['UpgradeSimpleRisk']) . "\" />\n";
    }

    echo "</form>";

    echo "
        <div class='progress-wrapper' style='display: none'>
            <div class='progress-window'>
            </div>
        </div>
    ";
}

/*****************************
 * FUNCTION: BACKUP DATABASE *
 *****************************/
function backup_database()
{
    global $lang;

    write_debug_log("UPGRADE EXTRA: FUNCTION[backup_database]: Checking for mysqldump.");

    // Get and check mysqldump service is available.
    if(!is_process('mysqldump')){
	 write_debug_log("UPGRADE EXTRA: FUNCTION[backup_database]: Could not find mysqldump.  Obtaining path from SimpleRisk settings instead.");
        $mysqldump_path = get_setting('mysqldump_path');
    }else{
	write_debug_log("UPGRADE EXTRA: FUNCTION[backup_database]: Found mysqldump path.");
        $mysqldump_path = "mysqldump";
    }
    
    if(!is_process(escapeshellcmd($mysqldump_path))){
	write_debug_log("UPGRADE EXTRA: FUNCTION[backup_database]: Unable to determine a valid mysqldump service on this server.");
        set_alert(true, "bad", $lang['UnavailableMysqldumpService']);
        return false;
    }

    // Export filename
    $filename = "simplerisk-" . date('Ymd') . ".sql";

    write_debug_log("UPGRADE EXTRA: FUNCTION[backup_database]: Generating file named {$filename}.");

    header("Pragma: public", true);
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");
    header("Content-Disposition: attachment; filename=".$filename);
    header("Content-Transfer-Encoding: binary");

    // Get the mysqldump command
    $cmd = upgrade_get_mysqldump_command();

    // Execute the mysqldump
    $mysqldump = system($cmd);

    write_debug_log("UPGRADE EXTRA: FUNCTION[backup_database]: Running mysqldump with command {$cmd}.");

    // Open memory as a file so no temp file needed
    $f = fopen('php://output', 'w');

    // Write the dump to the file
    fwrite($f, $mysqldump);

    // Close the file
    fclose($f);

    // Exit so that page content is not included in the results
    exit(0);
}

/*  Functions to write to the response stream.
    The delay is there to make sure the response is flushed and we're not
    just sending the response in a single line.
*/
function stream_write($text) {

    global $escaper;

    echo "<div>" . $escaper->escapeHtml($text) . "</div>";
    usleep(500000);
}
function stream_write_error($text) {

    global $escaper;

    echo "<div class='error_message'>" . $escaper->escapeHtml($text) . "</div>";
    usleep(500000);
}

/************************************************************
 * Function BACKUP                                          *
 * Doing the Application and DB backup.                     *
 * ATM the Application backup is just simply copying over,  *
 * we can improve on it later.                              *
 ************************************************************/
function backup() {

    global $lang;

    stream_write($lang['BackupStart']);

    $source = $simplerisk_dir = realpath(__DIR__) . '/../';
    $timestamp = date("Y-m-d--H-i-s");
    $target_dir_root = sys_get_temp_dir() . '/simplerisk-backup-' . $timestamp;
    $target_dir_app = $target_dir_root . '/app';
    $target_dir_db = $target_dir_root . '/db';

    stream_write($lang['BackupCheckingPreRequisites']);
    // If the backup directory does not exist
	if (!is_dir($target_dir_root)) {

		// If the temp directory is not writeable
		if (!is_writeable(sys_get_temp_dir())) {
            stream_write_error(_lang('BackupDirectoryNotWriteable', array('location' => sys_get_temp_dir()), false));
			return false;
		}

		// If the backup directory structure can not be created
		if (!mkdir($target_dir_root) || !mkdir($target_dir_app) || !mkdir($target_dir_db)) {
            stream_write_error(_lang('BackupFailedToCreateDirectories', array('location' => sys_get_temp_dir()), false));
			return false;
		}
	}

    // Get and check mysqldump service is available.    
    if(!is_process('mysqldump')) {
        $mysqldump_path = get_setting('mysqldump_path');
    } else {
        $mysqldump_path = "mysqldump";
    }

    $db_backup_file = $target_dir_db . '/simplerisk-db-backup-' . $timestamp . '.sql';

    // Get the mysqldump command
    $cmd = upgrade_get_mysqldump_command();

    // Add the output redirect to the mysqldump command
    $db_backup_cmd = $cmd . ' > ' . escapeshellarg($db_backup_file);

    stream_write($lang['BackupCheckingPreRequisitesDone']);

    //Copy the app files over
    stream_write($lang['BackupApplicationFiles']);
    recurse_copy($source, $target_dir_app);
    stream_write($lang['BackupApplicationFilesDone']);

    // Backup the database
    stream_write($lang['BackupDatabase']);
    $mysqldump = system($db_backup_cmd);
    stream_write($lang['BackupDatabaseDone']);

    stream_write($lang['BackupSuccessful']);
    return true;
}

/*********************************
 * FUNCTION: UPGRADE APPLICATION *
 *********************************/
function upgrade_application()
{
    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Checking for configured proxy server.");

    // Configure the proxy server if one exists
    if (function_exists("set_proxy_stream_context"))
    {
	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Configuring to work with the defined proxy configuration.");

        // Configuration for the download request
        $method = "GET";
        $header = "Content-Type: application/x-www-form-urlencoded";
        $context = set_proxy_stream_context($method, $header);
    }

    // Get the current application version
    $current_version = current_version("app");

    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Current application version is {$current_version}.");

    // Get the next application version
    $next_version = next_version($current_version);

    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Next application version is {$next_version}.");

    // If the current version is not the latest
    if ($next_version != "")
    {
	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: An upgrade to the next version is required.");

        echo "Update required<br />\n";

        // Get the file name for the next version to upgrade to
        $file_name = "simplerisk-" . $next_version;

	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: File name of next application version is {$file_name}.");
	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Checking for an existing tgz file with that name in the system temporary directory.");

        // Delete current files
        echo "Deleting existing tgz file.<br />\n";
        if (file_exists(sys_get_temp_dir() . "/" . $file_name . ".tgz"))
	    {
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Found an existing tgz file with that name.  Attempting to remove it.");

		    try
		    {
		    	unlink(sys_get_temp_dir() . "/" . $file_name . ".tgz");
		    }
		    catch(Exception $e)
		    {
		    	write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Unable to remove the file.  Check file permissions.");

		    	echo "WARNING: Unable to delete " . sys_get_temp_dir() . "/" . $file_name . ".tgz";
		    }
	    }

	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Checking for an existing tar file with that name in the sytem temporary directory.");

	    echo "Deleting existing tar file.<br />\n";

        if (file_exists(sys_get_temp_dir() . "/" . $file_name . ".tar"))
	    {
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Found an existing tar file with that name.  Attempting to remove it.");

		    try
		    {
			    unlink(sys_get_temp_dir() . "/" . $file_name . ".tar");
		    }
		    catch(Exception $e)
		    {
			    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Unable to remove the file.  Check file permissions.");

			    echo "WARNING: Unable to delete " . sys_get_temp_dir() . "/" . $file_name . ".tar";
		    }
	    }

	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Checking for an existing config.php file in the system temporary directory.");

	    echo "Deleting existing config.php file.<br />\n";

        if (file_exists(sys_get_temp_dir() . "/config.php"))
	    {
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Found an existing config.php file with that name.  Attempting to remove it.");
		    try
		    {
		    	unlink(sys_get_temp_dir() . "/config.php");
		    }
		    catch (Exception $e)
		    {
		    	write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Unable to remove the file.  Check file permissions.");

		    	echo "WARNING: Unable to delete " . sys_get_temp_dir() . "/config.php";
		    }
	    }

	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Attempting to download the updated SimpleRisk bundle.");

        // Download the file to tmp
        echo "Downloading the updated SimpleRisk bundle.<br />\n";
                
        // If the bundles URL is defined 
        if (defined('BUNDLES_URL'))
        {       
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: A custom BUNDLES_URL value has been defined.");

            // Get today's date
            $today = date("Y-m-d");
                        
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Bundle URL is " . BUNDLES_URL . "/simplerisk-" . $today . "-001.tgz.");
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Attempting to write the file to the system temporary directory.");

            // Get the bundle from that URL with today's date
            $file_put_contents_result = file_put_contents(sys_get_temp_dir() . "/" . $file_name . ".tgz", fopen(BUNDLES_URL . "/simplerisk-" . $today . "-001.tgz", 'r'));
        }      
        // Download the new release file to the temporary directory
	    else
	    {
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Bundle URL is https://simplerisk-downloads.s3.amazonaws.com/public/bundles/" . $file_name . ".tgz.");
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Attempting to write the file to the system temporary directory.");

		    $file_put_contents_result = file_put_contents(sys_get_temp_dir() . "/" . $file_name . ".tgz", fopen("https://simplerisk-downloads.s3.amazonaws.com/public/bundles/" . $file_name . ".tgz", 'r', false, $context));
	    }

	    // If the file_put_contents did not write data
	    if ($file_put_contents_result === false)
	    {
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Writing data to the file in the system temporary directory failed.  Check file permissions.");

		    // TODO: We should probably fail here if we did not successfully write the results
	    }
	    // If the file_put_contents wrote date
	    else
	    {
	    	write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Wrote {$file_put_contents_result} bytes to the file in the system temporary directory.");
	    }

        // Path to the SimpleRisk directory
        $simplerisk_dir = realpath(__DIR__ . "/../../");

	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Locating the SimpleRisk directory under {$simplerisk_dir}.");
	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Making a backup of the SimpleRisk config.php file to the system temporary directory.");

        // Backup the config file to tmp
        echo "Backing up the config file.<br />\n";
        $copy_result = copy ($simplerisk_dir . "/includes/config.php", sys_get_temp_dir() . "/config.php");

	    // If the copy was not successful
	    if ($copy_result === false)
	    {
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Copying the config file to the system temporary directory failed.  Check file permissions.");

		    // TODO: We should probably fail here if we did not successfully write the config.php file
	    }
	    // If the copy was successful
	    else
	    {
	    	write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Backup of the SimpleRisk config.php file was successful.");
	    }

	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Decompressing the downloaded tgz file.");

        // Decompress from gz
        echo "Decompressing the downloaded file.<br />\n";
        $p = new PharData(sys_get_temp_dir() . "/" . $file_name . ".tgz");
        $p->decompress();

	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Extracting the tar to the existing SimpleRisk directory.");

        // Extract the tar to the existing simplerisk directory
        echo "Extracting the downloaded file.<br />\n";
        $phar = new PharData(sys_get_temp_dir() . "/" . $file_name . ".tar");
        $phar_extract_result = $phar->extractTo(realpath($simplerisk_dir . "/../"), null, true);

	    // If the phar extract was not successful
	    if ($phar_extract_result === false)
	    {
	    	write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Extracting the tar to the SimpleRisk directory failed.  Check file permissions.");

	    	// TODO: We should probably fail here if we did not successfully extract the tar
	    }
	    // If the phar extract was succesful
	    else
	    {
	    	write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Extracting the tar file was successful.");
	    }

	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Replacing the default config.php file with the original.");

        // Copy the old config file back
        echo "Replacing the config file with the original.<br />\n";
        $copy_result = copy (sys_get_temp_dir() . "/config.php", $simplerisk_dir . "/includes/config.php");

	    // If the copy was not successful
        if ($copy_result === false)
        {
            write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Copying the config file to the simplerisk directory failed.  Check file permissions.");

            // TODO: We should probably fail here if we did not successfully write the config.php file
        }
        // If the copy was successful
        else
        {
            write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Replacing the the SimpleRisk config.php file was successful.");
        }

	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Checking for an existing tgz file in the system temporary directory.");

        // Clean up files
        echo "Cleaning up temporary files.<br />\n";
	    if (file_exists(sys_get_temp_dir() . "/" . $file_name . ".tgz"))
	    {
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Found the tgz file and attempting to remove it.");

		    try
		    {
        		unlink(sys_get_temp_dir() . "/" . $file_name . ".tgz");
		    }
		    catch (Exception $e)
            {
			    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Unable to remove the file.  Check file permissions.");

                echo "WARNING: Unable to delete " . sys_get_temp_dir() . "/" . $file_name . ".tgz";
            }
	    }

	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Checking for an existing tar file in the system temporary directory.");

	    if (file_exists(sys_get_temp_dir() . "/" . $file_name . ".tar"))
	    {
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Found the tar file and attempting to remove it.");

		    try
		    {
                unlink(sys_get_temp_dir() . "/" . $file_name . ".tar");
		    }
		    catch (Exception $e)
		    {
		    	write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Unable to remove the file.  Check file permissions.");

		    	echo "WARNING: Unable to delete " . sys_get_temp_dir() . "/" . $file_name . ".tar";
		    }
	    }

	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Checking for an existing config.php file in the system temporary directory.");

	    if (file_exists(sys_get_temp_dir() . "/config.php"))
	    {
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Found the config.php file and attempting to remove it.");

		    try
		    {
        		unlink(sys_get_temp_dir() . "/config.php");
		    }
		    catch (Exception $e)
		    {
			    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Unable to remove the file.  Check file permissions.");

			    echo "WARNING: Unable to delete " . sys_get_temp_dir() . "/config.php";
		    }
	    }

        // Get the current application version
        $current_version = current_version("app");

	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Current application version is {$current_version}.");

        // Get the next application version
        $next_version = next_version($current_version);

	    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Next application version is {$next_version}.");

        if ($next_version == "")
	    {
		    write_debug_log("UPGRADE EXTRA: FUNCTION[upgrade_application]: Application is at the current release and no further application upgrades are required.");

        	echo "Successfully updated to the latest version of SimpleRisk.\n";
	    }

    }
    else echo "You are already at the latest version of SimpleRisk.\n";
}

/**************************
 * FUNCTION: NEXT VERSION *
 **************************/
function next_version($current_version)
{
    // Configure the proxy server if one exists
    if (function_exists("set_proxy_stream_context"))
    {
        set_proxy_stream_context();
    }

    if (defined('UPDATES_URL'))
    {   
        $version_page = file(UPDATES_URL . '/upgrade_path.xml');
    }
    else $version_page = file('https://updates.simplerisk.com/upgrade_path.xml');

    $regex_pattern = "/<simplerisk-" . $current_version . ">(.*)<\/simplerisk-" . $current_version . ">/";

    foreach ($version_page as $line)
    {
        if (preg_match($regex_pattern, $line, $matches))
        {
            $next_version = $matches[1];
        }
    }

    // If the next version is set
    if (isset($next_version))
    {
        // Return the next version
        return $next_version;
    }
    else return "";
}

/*******************************************************************************
*
*   Leaving these version querying functions here for backward compatibility
*   2019/07/23
*
*
********************************************************************************/

/***********************************
 * FUNCTION: UPGRADE EXTRA VERSION *
 ***********************************/
if (!function_exists("upgrade_extra_version"))
{
function upgrade_extra_version()
{
    return UPGRADE_EXTRA_VERSION;
}
}

/******************************************
 * FUNCTION: AUTHENTICATION EXTRA VERSION *
 ******************************************/
if (!function_exists("authentication_extra_version"))
{
function authentication_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../authentication/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return AUTHENTICATION_EXTRA_VERSION;
        }
        else return "N/A";
}
}

/**************************************
 * FUNCTION: ENCRYPTION EXTRA VERSION *
 **************************************/
if (!function_exists("encryption_extra_version"))
{
function encryption_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../encryption/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return ENCRYPTION_EXTRA_VERSION;
    }
    else return "N/A";
}
}

/****************************************
 * FUNCTION: IMPORTEXPORT EXTRA VERSION *
 ****************************************/
if (!function_exists("importexport_extra_version"))
{
function importexport_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../import-export/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return IMPORTEXPORT_EXTRA_VERSION;
    }
    else return "N/A";
}   
}   
        
/****************************************
 * FUNCTION: NOTIFICATION EXTRA VERSION *
 ****************************************/
if (!function_exists("notification_extra_version"))
{       
function notification_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../notification/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return NOTIFICATION_EXTRA_VERSION;
    }
    else return "N/A";
}   
} 

/**************************************
 * FUNCTION: SEPARATION EXTRA VERSION *
 **************************************/
if (!function_exists("separation_extra_version"))
{
function separation_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../separation/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return SEPARATION_EXTRA_VERSION;
    }
    else return "N/A";
}
}

/***************************************
 * FUNCTION: ASSESSMENTS EXTRA VERSION *
 ***************************************/
if (!function_exists("assessments_extra_version"))
{
function assessments_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../assessments/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return ASSESSMENTS_EXTRA_VERSION;
    }
    else return "N/A";
}
}

/*******************************
 * FUNCTION: API EXTRA VERSION *
 *******************************/
if (!function_exists("api_extra_version"))
{
function api_extra_version()
{   
    // Get the extra path
    $path = realpath(__DIR__ . '/../api/index.php');
    
    // If the extra is installed
    if (file_exists($path))
    {   
        // Include the extra
        require_once($path);
        
        // Return the version
        return API_EXTRA_VERSION;
    }
    else return "N/A";
}
}

/***********************************************
 * FUNCTION: COMPLIANCEFORGE SCF EXTRA VERSION *
 ***********************************************/
if (!function_exists("complianceforge_scf_extra_version"))
{
function complianceforge_scf_extra_version()
{
        // Get the extra path
        $path = realpath(__DIR__ . '/../complianceforgescf/index.php');

        // If the extra is installed
        if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return COMPLIANCEFORGE_SCF_EXTRA_VERSION;
    }
    else return "N/A";
}
}

/*****************************************
 * FUNCTION: CUSTOMIZATION EXTRA VERSION *
 *****************************************/
if (!function_exists("customization_extra_version"))
{
function customization_extra_version()
{   
    // Get the extra path
    $path = realpath(__DIR__ . '/../customization/index.php');
    
    // If the extra is installed
    if (file_exists($path))
    {   
        // Include the extra
        require_once($path);
        
        // Return the version
        return CUSTOMIZATION_EXTRA_VERSION;
    }
    else return "N/A";
}
}

/*******************************************
 * FUNCTION: ADVANCED SEARCH EXTRA VERSION *
 *******************************************/
if (!function_exists("advanced_search_extra_version"))
{
function advanced_search_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../advanced_search/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return ADVANCED_SEARCH_EXTRA_VERSION;
    }
    else return "N/A";
}
}

/********************************
 * FUNCTION: JIRA EXTRA VERSION *
 ********************************/
if (!function_exists("jira_extra_version"))
{
function jira_extra_version()
{   
    // Get the extra path
    $path = realpath(__DIR__ . '/../jira/index.php');
    
    // If the extra is installed
    if (file_exists($path))
    {   
        // Include the extra
        require_once($path);
        
        // Return the version
        return JIRA_EXTRA_VERSION;
    }
    else return "N/A";
}
}

/*******************************
 * FUNCTION: UCF EXTRA VERSION *
 *******************************/
if (!function_exists("ucf_extra_version"))
{
function ucf_extra_version()
{
   // Get the extra path
    $path = realpath(__DIR__ . '/../ucf/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return UCF_EXTRA_VERSION;
    }
    else return "N/A";
}
}

/****************************************************
 * FUNCTION: VULNERABILITY MANAGEMENT EXTRA VERSION *
 ****************************************************/
if (!function_exists("vulnmgmt_extra_version"))
{
function vulnmgmt_extra_version()
{
   // Get the extra path
    $path = realpath(__DIR__ . '/../vulnmgmt/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return VULNMGMT_EXTRA_VERSION;
    }
    else return "N/A";
}
}

/*************************************************
 * FUNCTION: CHECK IF THIS APP IS LATEST VERSION *
 *************************************************/
 // Not used anymore, leaving here for backward compatibility, can be removed in a later release
 // Comment added on 2019-12-14
function check_latest_version()
{
    $current_app_version = current_version("app");
    $next_app_version = next_version($current_app_version);
    $db_version = current_version("db");

    $need_update_app = false;
    $need_update_db = false;
    
    // If the current version is not the latest
    if ($next_app_version != "") {
        $need_update_app = true;
    }
    
    // If the app version is not the same as the database version
    if ($current_app_version != $db_version) {
        $need_update_db = true;
    } elseif ($need_update_app && $next_app_version != $db_version) {
        $need_update_db = true;
    }
    
    // Check if there are update app or db version
    if($need_update_app || $need_update_db)
    {
        return false;
    }
    else
    {
        return true;
    }
}

/*******************************
 * FUNCTION: IS UPGRADE NEEDED *
 *******************************/
 // Not used anymore, leaving here for backward compatibility, can be removed in a later release
 // Comment added on 2019-12-14
function is_upgrade_needed()
{
    // Get the current application version
    $current_version = current_version("app");

    // Get the next application version
    $next_version = next_version($current_version);

    // If the current version is not the latest
    if ($next_version != "")
    {
        // An upgrade is needed
        return true;
    }
    // An upgrade is not needed
    else return false;
}

/**********************************
 * FUNCTION: IS DB UPGRADE NEEDED *
 **********************************/
 // Not used anymore, leaving here for backward compatibility, can be removed in a later release
 // Comment added on 2019-12-14
function is_db_upgrade_needed()
{
    // Get the current application version
    $app_version = current_version("app");

    // Get the current database version
    $db_version = current_version("db");

    // If the app version is not the same as the database version
    if ($app_version != $db_version)
    {
        // An upgrade is needed
        return true;
    }
    // An upgrade is not needed
    else return false;
}


function api_download_extra($extra) {

    // Doing a pre-upgrade check to prevent starting an upgrade that might fail
    do_pre_upgrade_check();

    // Upgrade the Extra
    $result = upgrade_download_extra($extra);
    
    // If the extra was downloaded successfully
    if ($result === true) {
        global $lang;
        
        // Return a 200 response
        json_response(200, $lang['ExtraInstalledSuccessfully'], null);
    } else {
        // Return a 403 response with the error message from the download extra
        json_response(403, $result, null);
    }
}

/************************************************************
 * FUNCTION: DOWNLOAD EXTRA                                 *
 * Downloads and installs the specified extra               *
 * It either returns the error message to indicate why      *
 * it failed or returns true if the download was successful *
 ************************************************************/
function upgrade_download_extra($name) {
    
    global $available_extras, $escaper, $lang;
    
    if (!in_array($name, $available_extras)) {
        return _lang('UpdateExtraInvalidName', array('name' => $name));
    }
    
    // SimpleRisk directory
    $simplerisk_dir = realpath(__DIR__ . '/../../');

    // Extras directory
    $extras_dir = $simplerisk_dir . '/extras';
    
    // If the extras directory does not exist
    if (!is_dir($extras_dir))
    {
        // If the simplerisk directory is not writeable
        if (!is_writeable($simplerisk_dir)) {
            return _lang('UpdateExtraNoPermissionForSimpleriskDirectory', array('simplerisk_dir' => $simplerisk_dir));
        }
        
        // If the extras directory can not be created
        if (!mkdir($extras_dir)) {
            return _lang('UpdateExtraNoPermissionForExtrasDirectory', array('extras_dir' => $extras_dir));
        }
    }
    
    // Get the instance id
    $instance_id = get_setting("instance_id");
    
    // Get the services API key
    $services_api_key = get_setting("services_api_key");
    
    // Create the data to send
    $parameters = array(
        'action' => 'download_extra',
        'extra_name' => $name,
        'instance_id' => $instance_id,
        'api_key' => $services_api_key,
    );
    
    $response = simplerisk_service_call($parameters);
    $return_code = $response['return_code'];

    // If the SimpleRisk services call failed
    if ($return_code !== 200)
    {
        write_debug_log("Unable to communicate with the SimpleRisk services API");
        return $lang['FailedToDownloadExtra'];
    }
    else
    {
        $results = $response['response'];
        $results = array($results);

        if (preg_match("/<result>(.*)<\/result>/", $results[0], $matches)) {
            switch ($matches[1]) {
                case "Not Purchased":
                    return $lang['RequestedExtraIsNotPurchased'];
                case "Invalid Extra Name":
                    return $lang['RequestedExtraDoesNotExist'];
                case "Unmatched IP Address":
                    return $lang['InstanceWasRegisteredWithDifferentIp'];
                case "Instance Disabled":
                    return $lang['InstanceIsDisabled'];
                case "Invalid Instance or Key":
                    return $lang['InvalidInstanceIdOrKey'];
                default:
                    return $lang['FailedToDownloadExtra'];
            }
        } else {
            // Write the extra to a file in the temporary directory
            $extra_file = sys_get_temp_dir() . '/' . $name . '.tar.gz';

            // Try to remove the file to make sure we can create the new one
            delete_file($extra_file);

            //Check if we succeeded
            if (file_exists($extra_file)) {
                return $lang['FailedToCleanupExtraFiles'];
            }

            $result = file_put_contents($extra_file, $response['response']);

            // Decompress the extra file
            $buffer_size = 4096;
            $out_file_name = str_replace('.gz', '', $extra_file);
            $file = gzopen($extra_file, 'rb');
            $out_file = fopen($out_file_name, 'wb');
            while (!gzeof($file)) {
                fwrite($out_file, gzread($file, $buffer_size));
            }
            fclose($out_file);
            gzclose($file);

            // Extract the tar to the tmp directory
            $phar = new PharData(sys_get_temp_dir() . '/' . $name . ".tar");
            $phar->extractTo(sys_get_temp_dir(), null, true);

            // Copy to the extras directory
            $source = sys_get_temp_dir() . '/' . $name;
            $destination = $extras_dir . '/' . $name;
            recurse_copy($source, $destination);
            // Clean up files
            $file = sys_get_temp_dir() . '/' . $name . '.tar.gz';
            delete_file($file);
            $file = sys_get_temp_dir() . '/' . $name . '.tar';
            delete_file($file);
            delete_dir($source);

            // Return a success
            return true;
        }
    }
}

/*******************************************
 * FUNCTION: UPGRADE GET MYSQLDUMP COMMAND *
 *******************************************/
function upgrade_get_mysqldump_command()
{
    // Open the database connection
    $db = db_open();

    // Get the database version information
    $stmt = $db->prepare("SELECT VERSION() as version;");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $version = $row['version'];

    // MariaDB version looks like "10.5.12-MariaDB-log"
    // MySQL version looks like "8.0.23"

    // Turn all mysqldump options off by default
    $column_statistics = false;
    $set_gtid_purged = false;
    $lock_tables = false;
    $skip_add_locks = false;
    $no_tablespaces = false;

    // If the database is MariaDB
    if (preg_match('/MariaDB/', $version))
    {
        // MariaDB uses the lock-tables option
        $lock_tables = true;

        // MariaDB uses the skip-add-locks option
        $skip_add_locks = true;

        // MariaDB uses the no-tablespaces option
        $no_tablespaces = true;
    }
    // Otherwise this is MySQL
    else
    {
        // MySQL uses the set-gtid-purged option
        $set_gtid_purged = true;

        // MySQL uses the lock-tables option
        $lock_tables = true;

        // MySQL uses the skip-add-locks option
        $skip_add_locks = true;

        // MySQL uses the no-tablespaces option
        $no_tablespaces = true;

        // Split the version by the decimals
        $version = explode('.', $version);
        $major_version = $version[0];
        $minor_version = $version[1].".".$version[2];

        // If the version is MySQL 8 or higher
        if ($major_version >= 8)
        {
            // MySQL >= 8 uses the column-statistics option
            $column_statistics = true;
        }
    }

    // If mysqldump does not exist
    if(!is_process('mysqldump'))
    {
        // Get the path from the SimpleRisk configuration
        $mysqldump_path = get_setting('mysqldump_path');
    }
    // Otherwise use the defined path to mysqldump
    else $mysqldump_path = "mysqldump";

    // Start the mysqldump command
    $mysqldump_command = escapeshellcmd($mysqldump_path) . " --opt";

    // If column_statistics is enabled
    if ($column_statistics)
    {
        // Append the column statistics option
        $mysqldump_command .= " --column-statistics=0";
    }

    // If lock_tables is enabled
    if ($lock_tables)
    {
        // Append the lock tables option
        $mysqldump_command .= " --lock-tables=false";
    }

    // If skip_add_locks is enabled
    if ($skip_add_locks)
    {
        // Append the skip add locks option
        $mysqldump_command .= " --skip-add-locks";
    }

    // If no_tablespaces is enabled
    if ($no_tablespaces)
    {
        // Append the no tablespaces option
        $mysqldump_command .= " --no-tablespaces";
    }

    // If set_gtid_purged is enabled
    if ($set_gtid_purged)
    {
        // Append the set gtid purged option
        $mysqldump_command .= " --set-gtid-purged=OFF";
    }

    // Append the database connection information
    $mysqldump_command .= "  -h " . escapeshellarg(DB_HOSTNAME) . " -u " . escapeshellarg(DB_USERNAME) . " -p" . escapeshellarg(DB_PASSWORD) . " " . escapeshellarg(DB_DATABASE);

    // Return the mysqldump command
    return $mysqldump_command;
}

/************************************
 * FUNCTION: IS DIRECTORY WRITEABLE *
 ************************************/
function is_directory_writeable($directory)
{
    // Default is all files are writeable
    $all_files_writeable = true;

    // Get the list of all files in the directory
    $files = scandir($directory);

    // For each of the files found
    foreach ($files as $key => $value)
    {
        // If this is an actual file or directory
        if ($value != ".." && $value != ".")
        {
            // Get the path
            $path = $directory . DIRECTORY_SEPARATOR . $value;

            // If the path is not writeable
            if (!is_writeable($path))
            {
                $all_files_writeable = false;

                // Log the file that is not writeable
                write_debug_log("File is not writeable: {$path}");
            }

            // If this is a directory
            if (is_dir($path))
            {
                // Recursively call the function on the sub-directory
                $value_returned = is_directory_writeable($path);

                // If the value returned is false
                if ($value_returned === false)
                {
                    // Set all files writeable to false
                    $all_files_writeable = false;
                }
            }
        }
    }

    // Return whether all files are writeable
    return $all_files_writeable;
}

?>
