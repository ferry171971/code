<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/display.php'));

        // Include Zend Escaper for HTML Output Encoding
        require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
        $escaper = new Zend\Escaper\Escaper('utf-8');

        // Add various security headers
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");

        // If we want to enable the Content Security Policy (CSP) - This may break Chrome
        if (CSP_ENABLED == "true")
        {
                // Add the Content-Security-Policy header
                header("Content-Security-Policy: default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'");
        }

        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
		session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);
        session_start('SimpleRisk');

        // Include the language file
        require_once(language_file());

        require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

        // Check for session timeout or renegotiation
        session_check();

        // Check if access is authorized
        if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
        {
                header("Location: ../index.php");
                exit(0);
        }

	// Default is no alert
	$alert = false;

        // Check if access is authorized
        if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
        {
                header("Location: ../index.php");
                exit(0);
        }

	// Check if the default asset valuation was submitted
	if (isset($_POST['update_default_value']))
	{
		// If value is set and is numeric
		if (isset($_POST['value']) && is_numeric($_POST['value']))
		{
			$value = (int)$_POST['value'];

			// If the value is between 1 and 10
			if ($value >= 1 && $value <= 10)
			{
				// Update the default asset valuation
				update_default_asset_valuation($value);
			}
		}
	}

	// Check if the automatic asset valuation was submitted
	if (isset($_POST['update_auto_value']))
	{
		$min_value = $_POST['min_value'];
		$max_value = $_POST['max_value'];

		// If the minimum value is an integer >= 0
		if (is_numeric($min_value) && $min_value >= 0)
		{
			// If the maximum value is an integer
			if (is_numeric($max_value))
			{
				// Update the asset values
				$success = update_asset_values($min_value, $max_value);

				// If the update was successful
				if ($success)
				{
					// There is an alert message
					$alert = "good";
					$alert_message = "The asset valuation settings were updated successfully.";
				}
				else
				{
					// There is an alert message
					$alert = "bad";
					$alert_message = "There was an issue updating the asset valuation settings.";
				}
			}
			else
			{
				// There is an alert message
				$alert = "bad";
				$alert_message = "Please specify an integer for the maximum value.";
			}
		}
		else
		{
			// There is an alert message
			$alert = "bad";
			$alert_message = "Please specify an integer greater than or equal to zero for the minimum value.";
		}
	}

	// Check if the manual asset valuation was submitted
	if (isset($_POST['update_manual_value']))
	{
		// For each value range
		for ($i=1; $i<=10; $i++)
		{
			$id = $i;
			$min_name = "min_value_" . $i;
			$min_value = $_POST[$min_name];
			$max_name = "max_value_" . $i;
			$max_value = $_POST[$max_name];

			// If the min_value and max_value are numeric
			if (is_numeric($min_value) && is_numeric($max_value))
			{
				// Update the asset value
				$success = update_asset_value($id, $min_value, $max_value);

                                // If the update was successful
                                if ($success)
                                {
                                        // There is an alert message
                                        $alert = "good";
                                        $alert_message = "The asset valuation settings were updated successfully.";
                                }
                                else
                                {
                                        // There is an alert message
                                        $alert = "bad";
                                        $alert_message = "There was an issue updating the asset valuation settings.";
                                }
			}
		}
	}

?>

<!doctype html>
<html>
  
  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script language="javascript" src="../js/asset_valuation.js" type="text/javascript"></script>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css"> 
    <style type="text/css">
      #dollarsign {
      	background: white url(/images/dollarsign.jpg) left no-repeat;
	background-size: 15px;
	padding-left: 17px;
      }
    </style>
  </head>
  
  <body>
    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">

<?php
	view_top_menu("Configure");

        if ($alert == "good")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 greenalert\">" . $escaper->escapeHtml($alert_message) . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
        else if ($alert == "bad")
        {
                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                echo "<div class=\"row-fluid\">\n";
                echo "<div class=\"span12 redalert\">" . $escaper->escapeHtml($alert_message) . "</div>\n";
                echo "</div>\n";
                echo "</div>\n";
                echo "<br />\n";
        }
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <?php view_configure_menu("AssetValuation"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit">
                <form name="default" method="post" action="">
                <h4><?php echo $escaper->escapeHtml($lang['DefaultAssetValuation']); ?>:</h4>
                <table border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td><?php echo $escaper->escapeHtml($lang['Default']); ?>:&nbsp;</td>
                  <td>
                    <?php
			// Get the default asset valuation
			$default = get_default_asset_valuation();

			// Create the asset valuation dropdown
			create_asset_valuation_dropdown("value", $default);
                    ?>
                  </td>
                </tr>
                <tr>
                  <td colspan="2"><input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_default_value" /></td>
                </tr>
                </table>
                </form>
              </div>
              <div class="hero-unit">
                <form name="automatic" method="post" action="">
                <h4><?php echo $escaper->escapeHtml($lang['AutomaticAssetValuation']); ?>:</h4>
		<table border="0" cellpadding="0" cellspacing="0">
		<tr>
                  <td><?php echo $escaper->escapeHtml($lang['MinimumValue']); ?>:&nbsp;</td>
		  <td><input id="dollarsign" type="number" name="min_value" min="0" size="20" value="<?php echo asset_min_value(); ?>" /></td>
                </tr>
                <tr>
		  <td><?php echo $escaper->escapeHtml($lang['MaximumValue']); ?>:&nbsp;</td>
		  <td><input id="dollarsign" type="number" name="max_value" size="20" value="<?php echo asset_max_value(); ?>" /></td>
		</tr>
		<tr>
		  <td colspan="2"><input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_auto_value" /></td>
		</tr>
		</table>
                </form>
              </div>
              <div class="hero-unit">
                <form name="manual" method="post" action="">
                <p>
                <h4><?php echo $escaper->escapeHtml($lang['ManualAssetValuation']); ?>:</h4>
		<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_manual_value" />
		<?php display_asset_valuation_table(); ?>
		<input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_manual_value" />
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>

</html>
