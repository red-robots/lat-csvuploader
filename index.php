<?php
/**
 * Created by PhpStorm.
 * User: fritz
 * Date: 2/27/17
 * Time: 3:54 PM
 *
 *
 *This program matches a full contact database against an existing database of most recent information 
 * in order to find discrpancies in certain input fields
 *
 *Only the input of a SA/Linkedin file allows for the output of a download file.
 *
 *The FC database is replaced entirely with each FC upload as this is seen as accurate information
 *
 *
 */
define( 'IN_CODE', 1 );
//turnoff errors
error_reporting( 0 );
require_once( 'login.php' );
$errors = [];
$confirmed = [];
$changed = [];
$unconfirmed  = [];
$headers = [];
if ( isset( $_POST['download'] ) ) {
	try {
		$db   = new PDO( "mysql:host=localhost;dbname=" . DBNAME . ";charset=latin1", USERNAME, PASS, array(
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION
		) );
		$stmt = $db->query( 'SELECT * FROM bella_existing' );
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC );
		foreach ( $rows as $row ) {
			$line = array( $row['First Name'], $row['Last Name'], $row['Email'] );
			switch ( $row['status'] ) {
				case 0:
					$confirmed[] = $line;
					break;
				case 1:
					$changed[] = $line;
					break;
				case 3:
				case 2:
					$unconfirmed[] = $line;
					break;
			}
		}
	} catch ( PDOException $e ) {
		$errors[] = "Code 10: " . $e;
	}
} elseif ( isset( $_FILES['upload'] ) ) {
    /*
     * linkedin
     * first name 1
     * last name 3
     * email 5
     *
     * sa
     * name 1
     * email 4
     */
	$upload_name = $_FILES['upload']['name'];
	$file_size   = $_FILES['upload']['size'];
	$file_ext    = strtolower( end( explode( '.', $upload_name ) ) );
	$extensions  = array( "csv" );
	if ( in_array( $file_ext, $extensions ) === true && $file_size <= 2097152 ) {
		$full_filename = "uploads/" . $upload_name . strval( time() );
		if ( move_uploaded_file( $_FILES['upload']['tmp_name'], $full_filename ) ) {
			if ( $fh = fopen( $full_filename, "r" ) ) {
				try {
					$db         = new PDO( "mysql:host=localhost;dbname=" . DBNAME . ";charset=latin1", USERNAME, PASS, array(
						PDO::ATTR_EMULATE_PREPARES => false,
						PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION
					) );
					$i          = 0;
					$first_name = "";
					$last_name  = "";
					$email      = "";
					$status     = "";
					$linkedin_file = 0;
					//setup prepares
					$select_full_contact = $db->prepare( "SELECT * FROM bella_full_contact WHERE `Email`=:email" );
					$select_full_contact->bindParam( ':email', $email, PDO::PARAM_STR );
					$select_existing = $db->prepare( "SELECT * FROM bella_existing WHERE `Email`=:email" );
					$select_existing->bindParam( ':email', $email, PDO::PARAM_STR );
					$update_existing = $db->prepare( "UPDATE bella_existing SET `First Name`=:firstname, `Last Name`=:lastname WHERE `Email`=:email" );
					$update_existing->bindParam( ':email', $email, PDO::PARAM_STR );
					$update_existing->bindParam( ':firstname', $first_name, PDO::PARAM_STR );
					$update_existing->bindParam( ':lastname', $last_name, PDO::PARAM_STR );
					$insert_existing = $db->prepare( "INSERT INTO bella_existing(`First Name`,`Last Name`, `Email`) VALUES(:firstname,:lastname,:email)" );
					$insert_existing->bindParam( ':email', $email, PDO::PARAM_STR );
					$insert_existing->bindParam( ':firstname', $first_name, PDO::PARAM_STR );
					$insert_existing->bindParam( ':lastname', $last_name, PDO::PARAM_STR );
					if(isset($_POST['file_type'])){
					    if(strcasecmp($_POST['file_type'],'linkedin')===0){
					        $linkedin_file=1;
                        }
                    }
					while ( $line = fgetcsv( $fh ) ) {
						if ( $i === 0 ) {
							$i ++;
							$headers = $line;
							continue;
						}
                        if($linkedin_file){
                            $email      = trim( $line[4] );
                            $first_name = trim( $line[0] );
                            $last_name  = trim( $line[1] );
                        } else {
                            $email = trim($line[4]);
                            $names = explode(" ",trim($line[1]));
	                        $first_name = isset($names[0])?$names[0]:"";
	                        $last_name = isset($names[1])?trim(substr($line[1],strpos(trim($line[1])," ")+1)):"";
                        }
						if(!$email){
							continue;
						}
						$exists_full_contact = 0;
						$match_full_contact = 0;
						$exists_existing = 0;
						$match_existing = 0;
						$select_full_contact->execute();
						$select_existing->execute();
						$full_contact_row_count = $select_full_contact->rowCount();
						$existing_row_count = $select_existing->rowCount();
						$rows_full_contact = null;
						$rows_existing = null;
						if($full_contact_row_count) {
							$exists_full_contact = 1;
							$rows = $select_full_contact->fetchAll( PDO::FETCH_ASSOC );
							if(strcasecmp($rows[0]['First Name'],$first_name)===0&&strcasecmp($rows[0]['Last Name'],$last_name)===0){
								$match_full_contact=1;
							}
							$rows_full_contact = array('First Name'=>$rows[0]['First Name'],'Last Name'=>$rows[0]['Last Name'],'Email'=>$rows[0]['Email']);
						}
						if($existing_row_count) {
							$exists_existing = 1;
							$rows = $select_existing->fetchAll( PDO::FETCH_ASSOC );
							if(strcasecmp($rows[0]['First Name'],$first_name)===0&&strcasecmp($rows[0]['Last Name'],$last_name)===0){
								$match_existing=1;
							}
							$rows_existing = array('First Name'=>$rows[0]['First Name'],'Last Name'=>$rows[0]['Last Name'],'Email'=>$rows[0]['Email']);
						}
                        if($exists_existing&&$match_existing&&$exists_full_contact&&$match_full_contact){
						    //#3
                            $confirmed[] = array_merge(array("3","No Change At Source",""),$line);
                        } elseif($exists_existing&&$match_existing&&$exists_full_contact&&!$match_full_contact){
                            //#2
                            $changed[] = array_merge(array("2",$rows_full_contact['First Name'],$rows_full_contact['Last Name']),$line);
                        } elseif($exists_existing&&$match_existing&&!$exists_full_contact&&!$match_full_contact) {
	                        //#6&&#7
	                        $unconfirmed[] = array_merge(array( "7", "", ""),$line);
                        } elseif($exists_existing&&!$match_existing&&$exists_full_contact&&$match_full_contact){
                            //#8
                            $changed[] = array_merge(array("8",$rows_full_contact['First Name'],$rows_full_contact['Last Name']),$line);
                        } elseif($exists_existing&&!$match_existing&&$exists_full_contact&&!$match_full_contact){
	                        //#1
                            $changed[] = array_merge(array("1",$rows_full_contact['First Name'],$rows_full_contact['Last Name']),$line);
                        } elseif($exists_existing&&!$match_existing&&!$exists_full_contact&&!$match_full_contact){
	                        //#9
                            $changed[] = array_merge(array("9","",""),$line);
                        } elseif(!$exists_existing&&!$match_existing&&$exists_full_contact&&$match_full_contact) {
                            //#4
	                        $confirmed[] = array_merge(array("4",$rows_full_contact['First Name'],$rows_full_contact['Last Name']),$line);
                        } elseif(!$exists_existing&&!$match_existing&&$exists_full_contact&&!$match_full_contact) {
                            //#5
	                        $changed[] = array_merge(array("5",$rows_full_contact['First Name'],$rows_full_contact['Last Name']),$line);
                        } else {
                            //#6&&#7
	                        $unconfirmed[] = array_merge(array("6","",""),$line);
                        }
                        if($exists_existing&&!$match_existing){
                            $update_existing->execute();
                        } elseif(!$exists_existing){
                            $insert_existing->execute();
                        }
					}
				} catch ( PDOException $e ) {
					$errors[] = "Code 4: Error in pdo " . $e;
				}
				fclose( $fh );
				//unlink file, we don't store these
				if ( ! unlink( $full_filename ) ) {
					$errors[] = "Code 6: Couldn't unlink file";
				}
			} else {
				$errors[] = "code 7: Couldn't open file for reading";
			}
		} else {
			$errors[] = "code 1: Error in moving file to work with.";
		}
	} else {
		$errors[] = "code 2: That file type is not allowed.";
	}
} elseif ( isset( $_FILES['uploadfc'] ) ) {
	/*
	 * First name at 1
	 * Last name at 3
	 * Email at 47
	 */
	$upload_name = $_FILES['uploadfc']['name'];
	$file_size   = $_FILES['uploadfc']['size'];
	$file_ext    = strtolower( end( explode( '.', $upload_name ) ) );
	$extensions  = array( "csv" );
	if ( in_array( $file_ext, $extensions ) === true && $file_size <= 12097152 ) {
		$full_filename = "uploads/" . $upload_name . strval( time() );
		if ( move_uploaded_file( $_FILES['uploadfc']['tmp_name'], $full_filename ) ) {
			if ( $fh = fopen( $full_filename, "r" ) ) {
				try {
					$db = new PDO( "mysql:host=localhost;dbname=" . DBNAME . ";charset=latin1", USERNAME, PASS, array(
						PDO::ATTR_EMULATE_PREPARES => false,
						PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION
					) );
					$i          = 0;
					$first_name = "";
					$last_name  = "";
					$email      = "";
					//setup prepares
                    $db->exec("DELETE FROM bella_full_contact WHERE 1");
					$select_full_contact = $db->prepare( "SELECT * FROM bella_full_contact WHERE `Email`=:email" );
					$select_full_contact->bindParam( ':email', $email, PDO::PARAM_STR );
					$insert_full_contact = $db->prepare( "INSERT INTO bella_full_contact(`First Name`,`Last Name`, `Email`) VALUES(:firstname,:lastname,:email)" );
					$insert_full_contact->bindParam( ':email', $email, PDO::PARAM_STR );
					$insert_full_contact->bindParam( ':firstname', $first_name, PDO::PARAM_STR );
					$insert_full_contact->bindParam( ':lastname', $last_name, PDO::PARAM_STR );
					while ( $line = fgetcsv( $fh ) ) {
						if ( $i === 0 ) {
							$i ++;
							continue;
						}
						if ($i > 1000 ) {
						    break;
                        }
                        $first_name = trim($line[1]);
						$last_name = trim($line[3]);
						$emails = array(trim($line[47]),trim($line[50]),trim($line[53]));
						foreach($emails as $email) {
							if(!empty($email)) {
								$select_full_contact->execute();
								$row_count = $select_full_contact->rowCount();
								if ( ! $row_count ) {
									$insert_full_contact->execute();
								}
							}
						}
					}
				} catch ( PDOException $e ) {
					$errors[] = "Code 15: Error in pdo " . $e;
				}
				fclose( $fh );
				//unlink file, we don't store these
				if ( ! unlink( $full_filename ) ) {
					$errors[] = "Code 16: Couldn't unlink file";
				}
			} else {
				$errors[] = "code 17: Couldn't open file for reading";
			}
		} else {
			$errors[] = "code 18: Error in moving file to work with.";
		}
	} else {
		$errors[] = "code 19: That file type is not allowed.";
	}
}
//generate export file
$download_link = null;
if ( ! empty( $confirmed ) || ! empty( $unconfirmed ) || ! empty( $changed ) ) {
	$strong = false;
	$bytes  = bin2hex( openssl_random_pseudo_bytes( 50, $strong ) );
	if ( $strong ) {
		$download_link = "downloads/" . $bytes . ".csv";
		if ( $fh = fopen( $download_link, "w" ) ) {
			fputcsv( $fh, array_merge(array( "Status","FC First","FC Last" ),$headers));
			if ( ! empty( $confirmed ) ) {
				fputcsv( $fh, array( "Confirmed") );
				foreach ( $confirmed as $line ) {
					fputcsv( $fh, $line );
				}
				fputcsv( $fh, array() );
				fputcsv( $fh, array() );
				fputcsv( $fh, array() );
			}
			if ( ! empty( $unconfirmed ) ) {
				fputcsv( $fh, array( "Unconfirmed") );
				foreach ( $unconfirmed as $line ) {
					fputcsv( $fh, $line );
				}
				fputcsv( $fh, array() );
				fputcsv( $fh, array() );
				fputcsv( $fh, array() );
			}
			if ( ! empty( $changed ) ) {
				fputcsv( $fh, array( "Changed" ) );
				foreach ( $changed as $line ) {
					fputcsv( $fh, $line );
				}
				fputcsv( $fh, array() );
				fputcsv( $fh, array() );
				fputcsv( $fh, array() );
			}

			fclose( $fh );
		} else {
			$errors[]      = "code 9: Couldn't open file for writing";
			$download_link = null;
		}
	} else {
		$errors[] = "code 9: Couldn't create random file";
	}
}
if ( ! empty( $errors ) && LOGS ) {
	$dateTime = new DateTime();
	if ( $fh = fopen( "logs/" . $dateTime->format( 'Ymd' ) . ".log", "a" ) ) {
		foreach ( $errors as $error ) {
			fwrite( $fh, $error . "\n" );
		}
		fclose( $fh );
	}
}
//setup form upload to process ?>
<!DOCTYPE html>
<html>
<head>
    <title>LAT - CVS Processor</title>
</head>
<body>
<h1>File uploader</h1>
<p>Please upload file below and click submit</p>
<?php if ( $download_link ) {
	echo '<h2>Download Here</h2>';
	echo '<a href="' . $download_link . '">Download</a><br><br>';
} ?>
<?php if ( $errors ) {
	echo "<h2>Errors - please see log</h2>";
} ?>
<form enctype="multipart/form-data" action="" method="POST">
    <p>Below insert sa/linkedin file</p>
    <label>Select File Type</label>
    <select name="file_type">
        <option value="linkedin">Linkedin</option>
        <option value="sa">Scrum Alliance</option>
    </select>
    <input type="file" name="upload"/>
    <input type="submit" value="Submit"/>
</form>
<form enctype="multipart/form-data" action="" method="POST">
    <p>Below insert FC file</p>
    <input type="file" name="uploadfc"/>
    <input type="submit" value="FC Upload"/>
</form>
<!--
<h3>Download Complete Export File</h3>
<form action="" method="POST">
    <p>Download Complete FC file</p>
    <input type="hidden" name="download" value="download"/>
    <input type="submit" value="Download"/>
</form>-->
</body>
</html>