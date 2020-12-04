<!DOCTYPE HTML>

<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <!-- Optional Bootstrap theme -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">
    <script src="gen_validatorv4.js" type="text/javascript"></script>
</head>
<body style="background-color: lightgray;">
<?php require_once 'vendor/autoload.php';

use LdapTools\Configuration;
use LdapTools\DomainConfiguration;
use LdapTools\LdapManager;
use LdapTools\Operation\AuthenticationOperation;

?>
<div id="wrapper" >

    <div >
        <ol class="breadcrumb">
            <h1> TEST AN LDAP CONNECTION</h1>
        </ol>
    </div>

    <div class="row" style="margin: 10px;" >


        <div class="col-xs-6 panel panel-primary">
            <div class="panel-body">
                <div id='frmldap_errorloc' class='error_strings'></div>
                <form action="#" id="frmldap" name="frmldap" method="POST" >
                    <div class="form-group">
                        <label for="ldap_url" class="control-label">LDAP URL: </label>&nbsp;&nbsp;<input class="form-control" id="ldap_url" type="text" name="ldap_url" value="<?php echo  $_POST['ldap_url'];?>" />    Typical Format: ldap.mydomain.com
                    </div>
                    <div class="form-group">
                        <label for="ldap_url">LDAP BASE DN: </label>&nbsp;&nbsp;<input id="ldap_rdn" type="text" class="form-control" name="ldap_rdn"  value="<?php echo  $_POST['ldap_rdn'];?>"/>    Typical Example: PBS or XYZ
                    </div>
                    <div class="form-group">
                        <div class="control-group"><label class="control-label">LDAP Type:</label>
                            <select class="form-control select_fermata" id="ad_ldap_type" name="ad_ldap_type" >
                                <option value="ad" <?php if ($_POST['ad_ldap_type']=="ad") { ?> selected <?php } ?>>AD</option>
                                <option value="openldap" <?php if ($_POST['ad_ldap_type']!="ad") { ?> selected <?php } ?>>OpenLDAP</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="user_name">Test Username: </label>&nbsp;&nbsp;<input id="username" type="text" class="form-control" name="username" value="<?php echo  $_POST['username'];?>" /></div>
                    <div class="form-group">
                        <label for="pass_word">Password: </label>&nbsp;&nbsp;<input id="password" type="PASSWORD" class="form-control" name="password" value="<?php echo  $_POST['password'];?>" />  </div>
                    <div class="alert alert-danger">
                        NOTE:  Customers must grant access for txtaboutit Server IP Address 208.86.159.106 on TCP and UDP port 389, or on port 636 for LDAPS
                    </div>
                    <input type="submit" name="submit" class="btn-primary center-block" value="Submit" />

                </form>

                <script  type="text/javascript">
                    var frmvalidator = new Validator("frmldap");
                    frmvalidator.EnableOnPageErrorDisplaySingleBox();
                    frmvalidator.EnableMsgsTogether();
                    frmvalidator.addValidation("ldap_url","req","Please enter LDAP URL");
                    //frmvalidator.addValidation("ldap_rdn","req","Please enter LDAP RDN");
                    // frmvalidator.addValidation("username","req","Please enter a valid test login username");
                    //  frmvalidator.addValidation("password","req","Please enter a password");


                </script>
            </div></div>

        <div class="col-xs-6">
            <?php
            /**
             * Test LDAP Login
             */
            if(isset($_POST['username']) && isset($_POST['password'])){

                echo "<div class='alert alert-info'>";
                echo 'Connecting to LDAP Server...<br>';

                $ldapserver = $_POST['ldap_url'];
                $basedn = $_POST['ldap_rdn'];
                $ldap_username = $_POST['username'];
                $ldap_password = $_POST['password'];
                $ldap_type = $_POST['ad_ldap_type'];

                $config = new Configuration();


                // A domain configuration object. Requires a domain name, servers, username, and password.
                $domain = (new DomainConfiguration($ldapserver))

                    ->setServers([$ldapserver])
                    ->setBaseDn($basedn)
                    ->setUsername($ldap_username)
                    ->setPassword($ldap_password)
                    ->setLazyBind(true)
                    ->setLdapType($ldap_type);
                /*$altDomain = (new DomainConfiguration('foo.bar'))
                    ->setBaseDn('cn=read-only-admin,dc=example,dc=com')
                    ->setServers(['forumsys.com'])
                    ->setUsername('einstein')
                    ->setPassword('password')*/
                /*  ->setLazyBind(true)
                  ->setLdapType('openldap');*/
                $config->addDomain($domain);
// Defaults to the first domain added. You can change this if you want.
//$config->setDefaultDomain('');



                try {
                    // The LdapManager provides an easy point of access to some different classes.
                $ldap2 = new LdapManager($config);

// With your LdapManager class already instantiated...
                 $operation = (new AuthenticationOperation())->setUsername($ldap_username)->setPassword($ldap_password);
                $response = $ldap2->getConnection()->execute($operation);
                
                
                if (!$response->isAuthenticated()) {
          echo "Error validating password for '".$operation->getUsername()."': ".$response->getErrorMessage();
                }

                if ($response->isAuthenticated()) {
                    echo 'LDAP Connection and Authentication Successful!<br>';
                }
            } catch (\Exception $e) {
                echo "Error:! ".$e->getMessage();
                exit;
            }

                if ($response->isAuthenticated()) {

                    echo 'LDAP Connection and Authentication Successful!<br>';


                } else {  //IF NOT BIND
                    echo '<br>THE LDAP CONNECTION FAILED<br>';

                    echo "Error: ".$response->getErrorMessage();



                }

                echo "</div>";

            }?>

        </div>




    </div>  </div>

</body></html>