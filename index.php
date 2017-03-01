<?php
/**
 * Created by PhpStorm.
 * User: fritz
 * Date: 2/27/17
 * Time: 3:54 PM
 *
 *
 *
 * status codes:
 * 0 = confirmed
 * 1 = changed
 * 2 = unconfirmed
 *
 *
 */
session_start();
define( 'IN_CODE', 1 );
//turnoff errors
error_reporting(0);
require_once( 'login.php' );
$errors = [];
$message = null;
if(isset($_POST['logout'])){
    //from php manual
	$_SESSION = array();
	if (ini_get("session.use_cookies")) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params["path"], $params["domain"],
			$params["secure"], $params["httponly"]
		);
	}
	session_destroy();
} elseif(isset($_POST['username']) && !isset($_SESSION['logged_in'])) {
	$db    = new PDO( "mysql:host=localhost;dbname=" . DBNAME . ";charset=latin1", USERNAME, PASS, array(
		PDO::ATTR_EMULATE_PREPARES => false,
		PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION
	) );
	$username = $_POST['username'];
	$stmt  = $db->prepare( "SELECT * FROM users WHERE `username`=:username" );
	$stmt->bindParam( ':username', $username, PDO::PARAM_STR );
	$stmt->execute();
	$rows_count = $stmt->rowCount();
	if ( $rows_count > 1 ) {
		$errors[] = "too many users";
	} elseif ( $rows_count === 1 ) {
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$row  = $rows[0];
		if ( password_verify( $_POST['pass'], $row['password'] ) ) {
            $_SESSION['logged_in']=1;
		} else {
			$message = "Those credentials didn't match";
        }
	} else {
		$message = "Those credentials didn't match";
	}
} elseif (isset($_SESSION['logged_in'])) {
	if ( isset( $_POST['download'] ) ) {
		try {
			$db   = new PDO( "mysql:host=localhost;dbname=" . DBNAME . ";charset=latin1", USERNAME, PASS, array(
				PDO::ATTR_EMULATE_PREPARES => false,
				PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION
			) );
			$stmt = $db->query( 'SELECT * FROM existing' );
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
	}
	if ( isset( $_FILES['upload'] ) ) {
		$upload_name = $_FILES['upload']['name'];
		$file_size   = $_FILES['upload']['size'];
		$file_ext    = strtolower( end( explode( '.', $upload_name ) ) );
		$expensions  = array( "csv" );
		if ( in_array( $file_ext, $expensions ) === true && $file_size <= 2097152 ) {
			if ( move_uploaded_file( $_FILES['upload']['tmp_name'], "uploads/" . $upload_name ) ) {
				$full_filename = "uploads/" . $upload_name;
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
						//setup prepares
						$select_matching = $db->prepare( "SELECT * FROM matching WHERE `Email`=:email" );
						$select_matching->bindParam( ':email', $email, PDO::PARAM_STR );
						$select_existing = $db->prepare( "SELECT * FROM existing WHERE `Email`=:email" );
						$select_existing->bindParam( ':email', $email, PDO::PARAM_STR );
						$update_matching = $db->prepare( "UPDATE matching SET `First Name`=:firstname, `Last Name`=:lastname WHERE `Email`=:email" );
						$update_matching->bindParam( ':email', $email, PDO::PARAM_STR );
						$update_matching->bindParam( ':firstname', $first_name, PDO::PARAM_STR );
						$update_matching->bindParam( ':lastname', $last_name, PDO::PARAM_STR );
						$update_existing = $db->prepare( "UPDATE existing SET `First Name`=:firstname, `Last Name`=:lastname, `status`=:status WHERE `Email`=:email" );
						$update_existing->bindParam( ':email', $email, PDO::PARAM_STR );
						$update_existing->bindParam( ':firstname', $first_name, PDO::PARAM_STR );
						$update_existing->bindParam( ':lastname', $last_name, PDO::PARAM_STR );
						$update_existing->bindParam( ':status', $status, PDO::PARAM_STR );
						$insert_existing = $db->prepare( "INSERT INTO existing(`First Name`,`Last Name`, `Email`,`status`) VALUES(:firstname,:lastname,:email,:status)" );
						$insert_existing->bindParam( ':email', $email, PDO::PARAM_STR );
						$insert_existing->bindParam( ':firstname', $first_name, PDO::PARAM_STR );
						$insert_existing->bindParam( ':lastname', $last_name, PDO::PARAM_STR );
						$insert_existing->bindParam( ':status', $status, PDO::PARAM_STR );
						$insert_matching = $db->prepare( "INSERT INTO matching(`First Name`,`Last Name`, `Email`) VALUES(:firstname,:lastname,:email)" );
						$insert_matching->bindParam( ':firstname', $first_name, PDO::PARAM_STR );
						$insert_matching->bindParam( ':lastname', $last_name, PDO::PARAM_STR );
						$insert_matching->bindParam( ':email', $email, PDO::PARAM_STR );
						while ( $line = fgetcsv( $fh ) ) {
							if ( $i === 0 ) {
								$i ++;
								continue;
							}
							$email      = $line[5];
							$first_name = $line[1];
							$last_name  = $line[3];
							if ( $email === null ) {
								continue;
							}
							$select_matching->execute();
							$select_matching_row_count = $select_matching->rowCount();
							if ( $select_matching_row_count > 1 ) {
								$errors[] = "Code 5: Too many rows";
							} elseif ( $select_matching_row_count === 1 ) {
								//matching row exists
								$select_matching_rows = $select_matching->fetchAll( PDO::FETCH_ASSOC );
								$select_matching_row  = $select_matching_rows[0];
								//is there existing contact row
								$select_existing->execute();
								$select_existing_row_count = $select_existing->rowCount();
								if ( $select_existing_row_count > 1 ) {
									$errors[] = "Code 8: Too many rows";
								} elseif ( $select_existing_row_count === 1 ) {
									//existing record exists
									$select_existing_rows      = $select_existing->fetchAll( PDO::FETCH_ASSOC );
									$select_existing_row       = $select_existing_rows[0];
									$select_existing_row_array = array(
										$select_existing_row['First Name'],
										$select_existing_row['Last Name'],
										$select_existing_row['Email']
									);
									//match first name last name
									if ( strcmp( $select_matching_row['First Name'], $first_name ) === 0 && strcmp( $select_matching_row['Last Name'], $last_name ) === 0 ) {
										if ( $select_existing_row['status'] === 0 ) {
											//add existing contact row to confirmed file
											$confirmed[] = $select_existing_row_array;
										} elseif ( $select_existing_row['status'] === 1 ) {
											//add exisiting contact row to changed file
											$changed[] = $select_existing_row_array;
										} else {
											//add existing row to unconfirmed file
											$unconfirmed[] = $select_existing_row_array;
										}
									} else {
										//does not match first last
										//update matching row
										$update_matching->execute();
										if ( $update_matching->rowCount() === 1 ) {
											//mark as changed in db
											if ( $select_existing_row['status'] === 2 ) {
												$status = 3;
											} else {
												$status = 1;
											}
											$update_existing->execute();
											if ( $update_existing->rowCount() === 1 ) {
												//add row to changed file
												$changed[] = array( $first_name, $last_name, $email );
											} else {
												$errors[] = $update_existing->errorInfo();
											}
										} else {
											$errors[] = $update_matching->errorInfo();
										}
									}
								} else {
									//existing record does not exist
									//update matching row
									$update_matching->execute();
									if ( $update_matching->rowCount() === 1 ) {
										//add to existing contacts table
										$status = 2;
										$insert_existing->execute();
										if ( $insert_existing->rowCount() === 1 ) {
											//add row to unconfirmed file
											$unconfirmed[] = array( $line[1], $line[3], $line[5] );
										} else {
											$errors[] = $insert_existing->errorInfo();
										}
									} else {
										$errors[] = $update_matching->errorInfo();
									}
								}
							} else {
								//matching does not exits
								//add row to matching table
								$insert_matching->execute();
								if ( $insert_matching->rowCount() === 1 ) {
									//add row to existing table
									$status = 2;
									$insert_existing->execute();
									if ( $insert_existing->rowCount() === 1 ) {
										//add row to export as unconfirmed
										$unconfirmed[] = array( $line[1], $line[3], $line[5] );
									} else {
										$errors[] = $insert_existing->errorInfo();
									}
								} else {
									$errors[] = $insert_matching->errorInfo();
								}
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
	}
//generate export file
	$download_link = null;
	if ( ! empty( $confirmed ) || ! empty( $unconfirmed ) || ! empty( $changed ) ) {
		$bytes         = bin2hex( random_bytes( 50 ) );
		$download_link = "downloads/" . $bytes . ".csv";
		if ( $fh = fopen( $download_link, "w" ) ) {
			fputcsv( $fh, array( "First Name", "Last Name", "Email" ) );
			if ( ! empty( $confirmed ) ) {
				fputcsv( $fh, array( "Confirmed", "Status", "0" ) );
				foreach ( $confirmed as $line ) {
					fputcsv( $fh, $line );
				}
				fputcsv( $fh, array() );
				fputcsv( $fh, array() );
				fputcsv( $fh, array() );
			}
			if ( ! empty( $unconfirmed ) ) {
				fputcsv( $fh, array( "Unconfirmed", "Status", "2" ) );
				foreach ( $unconfirmed as $line ) {
					fputcsv( $fh, $line );
				}
				fputcsv( $fh, array() );
				fputcsv( $fh, array() );
				fputcsv( $fh, array() );
			}
			if ( ! empty( $changed ) ) {
				fputcsv( $fh, array( "Changed", "Status", "1" ) );
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
}
if(isset($_SESSION['logged_in'])){
    //setup form upload to process
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>LAT - CVS Processor</title>
    </head>
    <body>
    <form action="" method="POST">
        <input type="hidden" name="logout"/>
        <input type="submit" value="logout"/>
    </form>
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
        <input type="file" name="upload"/>
        <input type="submit" value="Submit"/>
    </form>
    <h3>Download Complete Export File</h3>
    <form action="" method="POST">
        <input type="hidden" name="download" value="download"/>
        <input type="submit" value="Download"/>
    </form>
    </body>
    </html>
<?php } else {
	//session not logged in ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>LAT - CVS Processor</title>
    </head>
    <body>
        <h1>Please log in</h1>
        <?php if($message){
            echo '<p>'.$message.'</p>';
        }?>
        <form action="" method="POST">
            <input type="text" name="username"/>
            <input type="password" name="pass"/>
            <input type="submit" value="Submit"/>
        </form>
    </body>
    </html>
<?php }?>