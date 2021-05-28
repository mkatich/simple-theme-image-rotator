<?php
    
/*
 Plugin Name: Simple Theme Image Rotator
 Plugin URI: http://www.hikingmike.com
 Description: Rotate your banner image (or other theme image) to show a different one each day, week, hour, etc., even random on each page load.
 Version: 0.9.3
 Author: Michael Katich
 Author URI: http://www.hikingmike.com
 
*/




// --- constants ---
define('STIR_URL', WP_CONTENT_URL.'/plugins/simple-theme-image-rotator/');
define('IMG_DIR', WP_CONTENT_DIR.'/stir-user-images/');
define('IMG_DIR_URL', WP_CONTENT_URL.'/stir-user-images/');
    
// --- variables ---
$img_upload_msg = '';
$img_delete_msg = '';
$max_image_file_size = 10000000;//max file size for image


// --- functionality for uploading a file ---

//Upload errors flag variable - initialized as 0 = no error.
//Will be changed to 1 if error occurs, and file won't be uploaded.
$upload_errors_flag = 0;


//Do upload work. Check if the form has been submitted.
if (isset($_POST['upload_file_submit'])){
    //read name of the file the user uploaded
    $uploaded_file = $_FILES['user_file1']['name'];
    
    //check it is not empty
    if ($uploaded_file){
        //get file name (as it is on user's computer)
        $filename = stripslashes($_FILES['user_file1']['name']);

        //get file extension, lower case format. Check it's acceptable
        $extension = strtolower(stir_get_extension($filename));
        if (!($extension == 'jpg' || $extension == 'jpeg' || $extension == 'png'
                || $extension == 'gif' || $extension == 'bmp' || $extension == 'svg')){
            //invalid extension, print error message
            $img_upload_msg .= '<p style="color: red">Unknown image file extension!</p>';
            $upload_errors_flag = 1;
        }
        else {
            //extension is fine
            
            //check the file size vs max size
            //$_FILES['image']['tmp_name'] is the temporary filename of the file
            //in which the uploaded file was stored on the server
            $size = filesize($_FILES['user_file1']['tmp_name']);
            if ($size > $max_image_file_size){
                $img_upload_msg .= '<p style="color: red">You have exceeded the size limit! ('.$max_image_file_size.' bytes )</p>';
                $upload_errors_flag = 1;
            }
            else {
                //file size is fine
                
                //prepare new name and path for where we'll save the file
                
                //check if image directory exists in uploads. If not, it's probably
                //first usage of this plugin and need to create it.
                if (!stir_have_img_dir())
                    wp_mkdir_p(IMG_DIR);//create directory if didn't exist
                
                if (!stir_have_img_dir()){
                    //unable to create directory
                    $img_upload_msg .= '<p style="color: red">Could not create images directory!</p>';
                    $upload_errors_flag = 1;
                }
                else {
                    //image directory is fine
                    
                    //set new filename/path to copy to from temp
                    $new_filename = IMG_DIR.$filename;

                    //copy file to new location and name and verify it has been copied
                    $copied = copy($_FILES['user_file1']['tmp_name'], $new_filename);
                    if (!$copied){
                        $img_upload_msg .= '<p style="color: red">Upload failed! Maybe a file with that name already exists.</p>';
                        $upload_errors_flag = 1;
                    }
                }
            }
        }

        //if no error message, make nice message
        if ($upload_errors_flag == 0)
            $img_upload_msg = '<p style="color: green">File <b>"'.$filename.'"</b> uploaded!</p>';
    }

}

//make the resulting image upload message available to the function that writes 
//the interface so it can be seen when page reloads
define('IMAGE_UPLOAD_MSG', $img_upload_msg);

    
//stir_get_extension() - helper function reads the extension of the file.
//Used to give error on upload of unknown image formats.
function stir_get_extension($str){
    $parts = explode('.', $str);
    return end($parts);
}
// --- end functionality for uploading a file ---



// --- functionality for deleting a file ---
if (isset($_GET['delete_img'])) {
    //delete image
    @unlink(IMG_DIR. $_GET['delete_img']);

    //set message to say deleted
    $img_delete_msg = '<p style="color: gray">File '.$_GET['delete_img'].' deleted</p>';

    //now remove the "&delete_img" from the path so that it's not attempted on a subsequent update
    $index_todel = strrpos($form_action, '&delete_img');
    $form_action = substr($form_action, 0, $index_todel);
}

//make the resulting image upload message available to the function that writes
//the interface so it can be seen when page reloads
define('IMAGE_DELETE_MSG', $img_delete_msg);
// --- end functionality for deleting a file ---



//stir_have_img_dir() function returns true if the expected image directory exists
function stir_have_img_dir() {
    if (is_dir(IMG_DIR))
        return true;
    else
        return false;
}


//stir_get_image_filenames() function returns sorted array of filenames in user images directory
function stir_get_image_filenames() {
    $filenames = array();
    if (stir_have_img_dir()){
        $file_paths = glob(IMG_DIR."*");//glob() gives sorted output by default
        foreach ($file_paths as $file_path) {
            $curr_file_path_parts = pathinfo($file_path);
            $curr_file_name = $curr_file_path_parts['basename'];
            $filenames[] = $curr_file_name;
        }
    }
    return $filenames;
}

//stir_get_curr_image() function checks the rotate period option, gets the user images,
//then calculates which image should be shown and returns it!
function stir_get_curr_image(){
    //get the rotate period option chosen
    $curr_rotate_period = get_option('stir_rotate_period_option');

    //get all the images and the count
    $images_array = stir_get_image_filenames();
    $num_images = count($images_array);
    $image_index = 0;

    //Check if using "random every pageload" or a time period.
    if ($curr_rotate_period == 0){
        //chose "random every pageload", use random index to pick image
        $image_index = rand(0, $num_images-1);
    }
    else {
        //chose a normal time period, calculate for that

        //find the number of rotate periods since the epoch
        //this variable will be the number of "months" since epoch or "30 second periods" since epoch etc.
        //we can then use it to modulus with the image count for picking an image
        $rotate_periods_since_epoch = stir_get_rotate_periods_since_epoch($curr_rotate_period);

        //calculate the index of which image to use!
        $image_index = $rotate_periods_since_epoch % $num_images;
    }
    
    return IMG_DIR_URL . $images_array[$image_index];
}

//stir_get_rotate_periods_since_epoch() function calculates the number of rotation periods
//based on the rotation period option chosen that have elapsed since the epoch.  This is
//done to have some base from which to begin rotate periods from.
function stir_get_rotate_periods_since_epoch($stir_rotate_period_option){

    //rotate_periods_since_epoch is the return value.
    //this variable will be the number of "months" since epoch or "30 second periods" since epoch etc.
    //we can then use it to modulus with the image count for picking an image
    $rotate_periods_since_epoch = 0;

    //need to get dates for right now and for the epoch to use for rotations for
    //things like month or year since those cannot be calculated from seconds
    
    //get right now date attributes
    $now_y = date("Y");//A full numeric representation of a year, 4 digits - 2016
    $now_m = date("n");//Numeric representation of a month, without leading zeros - 1 through 12
    $now_d = date("j");//Day of the month without leading zeros	- 1 to 31
    $now_weekofyear = date("W");//Week number of year, weeks starting on Monday (added in PHP 4.1.0). Example: 42 (the 42nd week in the year)
    //get epoch date attributes. Epoch (January 1 1970 00:00:00 GMT)
    $epoch_y = 1970;
    $epoch_m = 1;
    $epoch_d = 1;
    $epoch_weekofyear = 1;

    //can use seconds_since_epoch and other simple ones to help calculate some intervals
    $seconds_since_epoch = date('U');
    $minutes_since_epoch = $seconds_since_epoch/60;
    $hours_since_epoch = $minutes_since_epoch/60;
    $days_since_epoch = $hours_since_epoch/24;
    
    //some are more complicated such as weeks_since_epoch and months_since_epoch 
    //since they are based on standard intervals of time like days and minutes
    $years_since_epoch = $now_y - $epoch_y;
    $months_since_epoch = 12*$years_since_epoch + ($now_m - $epoch_m);//1970-01-01 to 1971-03-01 => 12*1 + (3-1) = 14 months
    $weeks_since_epoch = 52*$years_since_epoch + ($now_weekofyear - $epoch_weekofyear);//1970 1st week to 1971 3rd week => 52*1 + (3-1) = 54 weeks

    //now begin setting the $rotate_periods_since_epoch based on the option chosen
    switch ($stir_rotate_period_option) {

        case '1': //5 seconds
            $rotate_periods_since_epoch = $seconds_since_epoch/5;
            break;
        case '2': //30 seconds
            $rotate_periods_since_epoch = $seconds_since_epoch/30;
            break;
        case '3': //1 minute
            $rotate_periods_since_epoch = $minutes_since_epoch;
            break;
        case '4': //5 minutes
            $rotate_periods_since_epoch = $minutes_since_epoch/5;
            break;
        case '5': //10 minutes
            $rotate_periods_since_epoch = $minutes_since_epoch/10;
            break;
        case '6': //15 minutes
            $rotate_periods_since_epoch = $minutes_since_epoch/15;
            break;
        case '7': //30 minutes
            $rotate_periods_since_epoch = $minutes_since_epoch/30;
            break;
        case '8': //45 minutes
            $rotate_periods_since_epoch = $minutes_since_epoch/45;
            break;
        case '9': //1 hour
            $rotate_periods_since_epoch = $hours_since_epoch;
            break;
        case '10': //2 hours
            $rotate_periods_since_epoch = $hours_since_epoch/2;
            break;
        case '11': //3 hours
            $rotate_periods_since_epoch = $hours_since_epoch/3;
            break;
        case '12': //4 hours
            $rotate_periods_since_epoch = $hours_since_epoch/4;
            break;
        case '13': //6 hours
            $rotate_periods_since_epoch = $hours_since_epoch/6;
            break;
        case '14': //8 hours
            $rotate_periods_since_epoch = $hours_since_epoch/8;
            break;
        case '15': //12 hours
            $rotate_periods_since_epoch = $hours_since_epoch/12;
            break;
        case '16': //1 day
            $rotate_periods_since_epoch = $days_since_epoch;
            break;
        case '17': //2 day
            $rotate_periods_since_epoch = $days_since_epoch/2;
            break;
        case '18': //3 day
            $rotate_periods_since_epoch = $days_since_epoch/3;
            break;
        case '19': //5 day
            $rotate_periods_since_epoch = $days_since_epoch/5;
            break;
        case '20': //1 week
            $rotate_periods_since_epoch = $weeks_since_epoch;
            break;
        case '21': //2 weeks
            $rotate_periods_since_epoch = $weeks_since_epoch/2;
            break;
        case '22': //3 weeks
            $rotate_periods_since_epoch = $weeks_since_epoch/3;
            break;
        case '23': //1 month
            $rotate_periods_since_epoch = $months_since_epoch;
            break;
        case '24': //2 month
            $rotate_periods_since_epoch = $months_since_epoch/2;
            break;
        case '25': //3 month
            $rotate_periods_since_epoch = $months_since_epoch/3;
            break;
        case '26': //4 month
            $rotate_periods_since_epoch = $months_since_epoch/4;
            break;
        case '27': //6 month
            $rotate_periods_since_epoch = $months_since_epoch/6;
            break;
        case '28': //1 year
            $rotate_periods_since_epoch = $years_since_epoch;
            break;
    }
    return $rotate_periods_since_epoch;
}
    
    
function stir_get_html_id_status_msg() {
    $msg = "";
    $stir_image_html_class = get_option('stir_image_html_class');
    $stir_image_html_id = get_option('stir_image_html_id');
    
    $msg .= "<p>";
    if ($stir_image_html_class == '' && $stir_image_html_id == ''){
        $msg .= ""
        . "<span style=\"color: blue; font-weight: bold;\">"
        . "Please enter either your container's class or id in the respective text box above and save settings."
        . "</span> ";
    }
    else if ($stir_image_html_class != '' && $stir_image_html_id != ''){
        $msg .= ""
        . "<span style=\"color: blue; font-weight: bold;\">"
        . "Please enter a value in only one of the fields for the container's class or id in the text boxes above."
        . "</span> ";
    }
    else if ($stir_image_html_class != '' && !preg_match("/^[a-zA-Z][\w:.-]*$/", $stir_image_html_class)){
        $msg .= ""
        . "<span style=\"color: red; font-weight: bold;\">"
        . "The container class you entered doesn't follow HTML syntax rules for a class name."
        . "</span> ";
    }
    else if ($stir_image_html_id != '' && !preg_match("/^[a-zA-Z][\w:.-]*$/", $stir_image_html_id)){
        $msg .= ""
        . "<span style=\"color: red; font-weight: bold;\">"
        . "The container id you entered doesn't follow HTML syntax rules for an id."
        . "</span> ";
    }
    $msg .= ""
	. "Enter a value for only one of the two fields above - either the class name or the id of the HTML container for which "
    . "you wish to rotate background images. For example, in the default "
    . "theme Twenty Sixteen, entering <b>site-branding</b> for the class name "
    . "would enable rotating the background image of the site header "
    . "(found within header.php). "
    . "This may be different depending on which "
    . "theme you are using. Look through your theme's files to find the "
    . "correct container and it's class or id. This plugin cannot verify your entry here. "
    . "Enter it and check your results!";
    $msg .= "</p>";
    
    return $msg;
}

function stir_get_img_dir_status_msg() {
    $msg = "";
    if (!stir_have_img_dir()){
        //directory doesn't exist. maybe not created yet since user hasn't added an image yet
        $msg = "<p>This directory doesn't exist currently but should "
        . "be created when you upload your first image.</p>";
    }
    else if (count(stir_get_image_filenames()) <= 0) {
        $msg = "<p style=\"color: red;\">There are no images in your image "
        . "directory. Please add some images for the plugin to work.</p>";
    }
    //++LIMIT NUM IMAGES++ else if ($num_images > 5) {
    //++LIMIT NUM IMAGES++     echo "<span style=\"color: red\">The basic version of this plugin allows a maximum of 5 images. Please delete some images or update to the pro version at <a href=\"http://www.ududududud.com\">udududududud.com</a></span>";
    //++LIMIT NUM IMAGES++ }
    return $msg;
}

function stir_write_to_head(){
    //make sure an html class or id is specified, the image directory is good, and we have at least one image first
    $stir_image_html_class = get_option('stir_image_html_class');
    $stir_image_html_id = get_option('stir_image_html_id');
	if (stir_have_img_dir() && count(stir_get_image_filenames()) > 0){
        $curr_rotated_img = stir_get_curr_image();
		if ($stir_image_html_class != ''){
			echo "
			<style type=\"text/css\">
				.$stir_image_html_class {
					background: url('$curr_rotated_img') no-repeat;
					background-position: top left;
				}
			</style>
			";
		}
		else if ($stir_image_html_id != ''){
			echo "
			<style type=\"text/css\">
				#$stir_image_html_id {
					background: url('$curr_rotated_img') no-repeat;
					background-position: top left;
				}
			</style>
			";
		}
	}
}

function stir_write_js_for_view_img() {
    echo "
        <script type=\"text/javascript\">
        <!--
        function stir_open_img(url, name, args) {
            viewImgWindow = window.open(url, name, args);
            viewImgWindow.screenX = window.screenX;
            viewImgWindow.screenY = window.screenY;
            viewImgWindow.focus();
        }
        //-->
        </script>
        ";
}

function stir_admin_options() {

    $img_upload_msg = IMAGE_UPLOAD_MSG;
    $img_delete_msg = IMAGE_DELETE_MSG;

    //set the form's submit URI
    $form_action = $_SERVER["REQUEST_URI"];

    if (isset($_POST['stir_rotate_period_option']))
        update_option('stir_rotate_period_option', ($_POST['stir_rotate_period_option']));
    
    if (isset($_POST['stir_image_html_class']))
        update_option('stir_image_html_class', ($_POST['stir_image_html_class']));
    
    if (isset($_POST['stir_image_html_id']))
        update_option('stir_image_html_id', ($_POST['stir_image_html_id']));
    
    //set current value for rotate period
    $curr_rotate_period = get_option('stir_rotate_period_option');

?>
<div class="wrap">
    
    <h2>Simple Theme Image Rotator | Settings</h2>

    <hr>
    
    <form name="update_settings_form" action="<?php echo $form_action ?>" method="post">

		
        <div>
			<b>Rotate image period:</b>
			<select name="stir_rotate_period_option">
				<option <?php if ($curr_rotate_period == "0") echo "selected";?> value="0">random every pageload</option>
				<option <?php if ($curr_rotate_period == "1") echo "selected";?> value="1">5 seconds</option>
				<option <?php if ($curr_rotate_period == "2") echo "selected";?> value="2">30 seconds</option>
				<option <?php if ($curr_rotate_period == "3") echo "selected";?> value="3">1 minute</option>
				<option <?php if ($curr_rotate_period == "4") echo "selected";?> value="4">5 minutes</option>
				<option <?php if ($curr_rotate_period == "5") echo "selected";?> value="5">10 minutes</option>
				<option <?php if ($curr_rotate_period == "6") echo "selected";?> value="6">15 minutes</option>
				<option <?php if ($curr_rotate_period == "7") echo "selected";?> value="7">30 minutes</option>
				<option <?php if ($curr_rotate_period == "8") echo "selected";?> value="8">45 minutes</option>
				<option <?php if ($curr_rotate_period == "9") echo "selected";?> value="9">1 hour</option>
				<option <?php if ($curr_rotate_period == "10") echo "selected";?> value="10">2 hours</option>
				<option <?php if ($curr_rotate_period == "11") echo "selected";?> value="11">3 hours</option>
				<option <?php if ($curr_rotate_period == "12") echo "selected";?> value="12">4 hours</option>
				<option <?php if ($curr_rotate_period == "13") echo "selected";?> value="13">6 hours</option>
				<option <?php if ($curr_rotate_period == "14") echo "selected";?> value="14">8 hours</option>
				<option <?php if ($curr_rotate_period == "15") echo "selected";?> value="15">12 hours</option>
				<option <?php if ($curr_rotate_period == "16") echo "selected";?> value="16">1 day</option>
				<option <?php if ($curr_rotate_period == "17") echo "selected";?> value="17">2 days</option>
				<option <?php if ($curr_rotate_period == "18") echo "selected";?> value="18">3 days</option>
				<option <?php if ($curr_rotate_period == "19") echo "selected";?> value="19">5 days</option>
				<option <?php if ($curr_rotate_period == "20") echo "selected";?> value="20">1 week</option>
				<option <?php if ($curr_rotate_period == "21") echo "selected";?> value="21">2 weeks</option>
				<option <?php if ($curr_rotate_period == "22") echo "selected";?> value="22">3 weeks</option>
				<option <?php if ($curr_rotate_period == "23") echo "selected";?> value="23">1 month</option>
				<option <?php if ($curr_rotate_period == "24") echo "selected";?> value="24">2 months</option>
				<option <?php if ($curr_rotate_period == "25") echo "selected";?> value="25">3 months</option>
				<option <?php if ($curr_rotate_period == "26") echo "selected";?> value="26">4 months</option>
				<option <?php if ($curr_rotate_period == "27") echo "selected";?> value="27">6 months</option>
				<option <?php if ($curr_rotate_period == "28") echo "selected";?> value="28">1 year</option>
			</select>

			<p>
				Choose how often you want the image to change (on pageload).
			</p>
		</div>
		
        <div>
            <b>Image container's class</b>:
            <input type="text" name="stir_image_html_class" value="<?php echo get_option('stir_image_html_class');?>">
            (example: site-branding)
			
			<br>
            <b>Image container's id</b>:
            <input type="text" name="stir_image_html_id" value="<?php echo get_option('stir_image_html_id');?>">
			(example: masthead)

            <?php
                echo stir_get_html_id_status_msg();
            ?>
        </div>

        <div>
            <b>Images directory:</b> <?php echo IMG_DIR_URL; ?>

            <?php
                echo stir_get_img_dir_status_msg();
            ?>
        </div>

        <div class="submit" style="margin-left: 130px;">
            <input type="submit" name="update_settings_submit" value="Save Settings" />
        </div>

    </form>

    

    <h2>Upload image</h2>
    <?php echo $img_upload_msg ?>
    
    <form name="upload_form" action="<?php echo $form_action ?>" method="post" enctype="multipart/form-data">
    <p>
        <b>1.</b> <input id="user_file1" name="user_file1" type="file" style="width: 400px;" />
        <b>2.</b> <span class="submit">
            <input type="submit" name="upload_file_submit" value="Upload File" />
        </span>
        &nbsp;&nbsp;&nbsp;&nbsp;
        (Files with the same name are overwritten)
    </p>
    </form>

    <h2>My images in rotation</h2>
    <?php echo $img_delete_msg ?>

    <p>Click on links to view:</p>
    <ul style="margin-left: 35px;">
    <?php
        
    $images_array = (stir_get_image_filenames());
    if ($images_array == 0){
        echo "<span style=\"color: red\">Cannot find any image files. Please resolve any path errors above.</span>";
    }
    else {
        $num_images = count(stir_get_image_filenames());
        if ($num_images == 0) {
            echo "<span style=\"color: blue\">No images here. Upload one!</span>";
        }
        foreach ($images_array as $an_image){
            echo '<li style="list-style-type: circle;">
                <a href="javascript:void(0)" onClick="stir_open_img(\''.IMG_DIR_URL.$an_image.'\', \'header\', \'width=650,height=300,toolbar=no,menubar=no,status=no,scrollbars=yes,resizable=yes\')">'.$an_image.'</a>
                &nbsp;
                <a href="'. $_SERVER["REQUEST_URI"].'&delete_img='.$entry.'" style="color: #888;">delete</a>
                </li>';
        }
    }
    ?>
    </ul>

    <hr>

    <h2>About</h2>
    <p>
        Let me know if you like this plugin. 
		My website is <a href="http://www.hikingmike.com">hikingmike.com</a>.
		You can email me at mike at thatdomain.com.
    </p>

</div>
<?php
}
    
    
    
function stir_admin_menu() {
    add_submenu_page('options-general.php', 'Simple Theme Image Rotator', 'Simple Theme Image Rotator', 8, __FILE__, 'stir_admin_options');
}

//add actions below
    
//add plugin to admin menu
add_action('admin_menu', 'stir_admin_menu');

//++LIMIT NUM IMAGES++ $num_images = count(stir_get_image_filenames());
//++LIMIT NUM IMAGES++ if ($num_images <= 5){
	
//add javascript to head in plugin admin screen to allow opening a user image
add_action('admin_head', 'stir_write_js_for_view_img');

//add CSS and JS to head of site to execute plugin functionality (if we have everything needed)
add_action('wp_head', 'stir_write_to_head');
    
//++LIMIT NUM IMAGES++ }
?>