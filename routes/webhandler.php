<?php
use watrlabs\router\Routing;
use watrlabs\authentication;
use watrlabs\watrkit\pagebuilder;
use watrlabs\watrkit\sanitize;



function checkhelp() {
    echo "hi";
}

global $router; // IMPORTANT: KEEP THIS HERE!

$router->get("/", function() {
    $page = new pagebuilder;
    $page::get_template("index");
});

$router->get('/messages/compose', function(){
    $page = new pagebuilder;
    $page::get_template("compose");
});

$router->post('/messages/compose', function(){
    if(isset($_POST["subject"]) && isset($_POST["body"]) && isset($_POST["__EVENTTARGET"])){
        $subject = $_POST["subject"];
        $body = $_POST["body"];
        $userid = $_POST["__EVENTTARGET"];

        $auth = new authentication();
        $recipient = $auth->getuserbyid($userid);

        if($auth->hasaccount()){
            $currentuser = $auth->getuserinfo($_COOKIE["_ROBLOSECURITY"]);
            if($recipient !== null){

                global $db;

                

                $msgcount = $db->table("messages")->where("userfrom", $currentuser->id)->where("date", ">", time() - 60)->count();

                if($msgcount >= 3){
                    $page = new pagebuilder;
                    $page::get_template("compose", ["error"=>"You're sending messages too fast!"]);
                } else {
                    $insert = array(
                        "userfrom"=>$currentuser->id,
                        "userto"=>$recipient->id,
                        "subject"=>htmlspecialchars($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        "body"=>htmlspecialchars($body, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        "date"=>time()
                    );
                    $db->table("messages")->insert($insert);
                    $page = new pagebuilder;
                    $page::get_template("compose", ["success"=>"Message sent to " . $recipient->username . "!"]);
                }
                
            } else {
                $page = new pagebuilder;
                $page::get_template("compose", ["error"=>"Failed to send message."]);
            }
        } else {
            header("Location: /");
            die();
        }

    } else {
        $page = new pagebuilder;
        $page::get_template("compose", ["error"=>"Failed to send message. Are you sure you filled in everything?"]);
    }
});

$router->get("/users/{userid}/friends", function($userid) {
    $page = new pagebuilder;
    $page::get_template("user/friends", ["userid"=>$userid]);
});

$router->get("/Games.aspx", function(){
    header("Location: /games");
    die();
});

$router->get("/temp/create-asset", function(){
    $page = new pagebuilder;
    $page::get_template("temp/uploadasset");
});

$router->get("/temp/universe-creator", function(){
    $page = new pagebuilder;
    $page::get_template("temp/universe-creator");
});

$router->get("/users/{userid}/profile", function($userid) {
    $page = new pagebuilder;
    $page::get_template("user/profile", array("userid"=>$userid));
});

$router->get("/users/{userid}/inventory", function($userid) {
    $page = new pagebuilder;
    $page::get_template("user/inventory");
});

$router->get("/my/messages", function() {
    $page = new pagebuilder;
    $page::get_template("my/messages");
});

$router->get('/MEMBERSHIP/CREATIONDISABLED.aspx', function(){
    $page = new pagebuilder;
    $page::get_template("membership/creationdisabled");
});

$router->get('/Membership/NotApproved.aspx', function(){
    $page = new pagebuilder;
    $page::get_template("membership/notapproved");
});


$router->get('/admin', function() {
    $auth = new authentication();
    $auth->requiresession();
    $admin = $auth->getuserinfo();
    if (!$admin || (int)$admin->is_admin !== 1) {
        http_response_code(403);
        die('403 - Admin access required.');
    }
    $page = new pagebuilder;
    $page::get_template('admin');
});

$adminGuard = function() {
    $auth = new authentication();
    $auth->requiresession();
    $admin = $auth->getuserinfo();
    if (!$admin || (int)$admin->is_admin !== 1) { http_response_code(403); die("403 - Admin access required."); }
    return $admin;
};

$adminRedirect = function($message = null, $error = null) {
    $params = [];
    if ($message !== null) $params['message'] = $message;
    if ($error !== null) $params['error'] = $error;
    header('Location: /admin' . ($params ? '?' . http_build_query($params) : ''));
    die();
};

$router->post('/admin/grant-currency', function() use ($adminGuard, $adminRedirect) {
    global $db; $adminGuard();
    $userid=(int)($_POST['userid']??0); $robux=(int)($_POST['robux']??0); $tix=(int)($_POST['tix']??0);
    $user=$db->table('users')->where('id',$userid)->first();
    if(!$user || $robux<0 || $tix<0 || ($robux===0 && $tix===0)) $adminRedirect(null,'Invalid user or amount.');
    $db->table('users')->where('id',$userid)->update(['robux'=>(int)$user->robux+$robux,'tix'=>(int)$user->tix+$tix]);
    $adminRedirect('Currency granted to '.$user->username.'.');
});

$router->post('/admin/set-membership', function() use ($adminGuard, $adminRedirect) {
    global $db; $adminGuard(); $userid=(int)($_POST['userid']??0); $membership=$_POST['membership']??'None';
    $allowed=['None','BuildersClub','TurboBuildersClub','OutrageousBuildersClub'];
    if(!in_array($membership,$allowed,true)) $adminRedirect(null,'Invalid membership.');
    $user=$db->table('users')->where('id',$userid)->first(); if(!$user) $adminRedirect(null,'User not found.');
    $db->table('users')->where('id',$userid)->update(['membership'=>$membership]); $adminRedirect('Membership updated for '.$user->username.'.');
});

$router->post('/admin/set-admin', function() use ($adminGuard, $adminRedirect) {
    global $db; $admin=$adminGuard(); $userid=(int)($_POST['userid']??0); $isAdmin=(int)($_POST['is_admin']??0);
    if($userid===(int)$admin->id && $isAdmin===0) $adminRedirect(null,'You cannot remove your own admin permission from this panel.');
    $user=$db->table('users')->where('id',$userid)->first(); if(!$user) $adminRedirect(null,'User not found.');
    $db->table('users')->where('id',$userid)->update(['is_admin'=>$isAdmin?1:0]); $adminRedirect('Admin permission updated.');
});

$router->post('/admin/grant-asset', function() use ($adminGuard, $adminRedirect) {
    global $db; $adminGuard(); $userid=(int)($_POST['userid']??0); $assetid=(int)($_POST['assetid']??0);
    $user=$db->table('users')->where('id',$userid)->first(); $asset=$db->table('assets')->where('id',$assetid)->first();
    if(!$user || !$asset) $adminRedirect(null,'User or asset not found.');
    $owned=$db->table('ownedassets')->where('userid',$userid)->where('assetid',$assetid)->first();
    if($owned) $adminRedirect(null,'User already owns this asset.');
    $db->table('ownedassets')->insert(['userid'=>$userid,'assetid'=>$assetid,'time'=>time()]); $adminRedirect('Granted asset #'.$assetid.' to '.$user->username.'.');
});

$router->post('/admin/moderate', function() use ($adminGuard, $adminRedirect) {
    global $db; $adminGuard(); $userid=(int)($_POST['userid']??0); $action=$_POST['action']??'warning'; $days=max(1,min(3650,(int)($_POST['days']??1))); $note=trim($_POST['note']??'');
    $user=$db->table('users')->where('id',$userid)->first(); if(!$user || $note==='') $adminRedirect(null,'User and moderation note are required.');
    $isBan=$action==='ban';
    $db->table('moderation')->insert(['userid'=>$userid,'type'=>$isBan?'days':'warning','reviewed'=>0,'banneduntil'=>$isBan?time()+($days*86400):time(),'moderatornote'=>substr($note,0,255),'offensiveitem'=>null,'days'=>$isBan?$days:null,'canignore'=>$isBan?0:1]);
    $adminRedirect($isBan?'User banned for '.$days.' day(s).':'Warning added to '.$user->username.'.');
});

$router->post('/admin/send-message', function() use ($adminGuard, $adminRedirect) {
    global $db; $admin=$adminGuard(); $userid=(int)($_POST['userid']??0); $subject=trim($_POST['subject']??''); $body=trim($_POST['body']??'');
    $user=$db->table('users')->where('id',$userid)->first(); if(!$user || $subject==='' || $body==='') $adminRedirect(null,'Recipient, subject and body are required.');
    $db->table('messages')->insert(['userfrom'=>(int)$admin->id,'userto'=>$userid,'subject'=>htmlspecialchars($subject,ENT_QUOTES|ENT_HTML5,'UTF-8'),'body'=>htmlspecialchars($body,ENT_QUOTES|ENT_HTML5,'UTF-8'),'date'=>time(),'hasread'=>0]);
    $adminRedirect('Message sent to '.$user->username.'.');
});

$router->post('/admin/delete-user', function() use ($adminGuard, $adminRedirect) {
    global $db; $admin=$adminGuard(); $userid=(int)($_POST['userid']??0); if($userid===(int)$admin->id) $adminRedirect(null,'You cannot delete yourself.');
    $user=$db->table('users')->where('id',$userid)->first(); if(!$user) $adminRedirect(null,'User not found.');
    $db->table('ownedassets')->where('userid',$userid)->delete(); $db->table('messages')->where('userfrom',$userid)->delete(); $db->table('messages')->where('userto',$userid)->delete(); $db->table('moderation')->where('userid',$userid)->delete(); $db->table('users')->where('id',$userid)->delete();
    $adminRedirect('Deleted user '.$user->username.'.');
});

$router->post('/admin/delete-asset', function() use ($adminGuard, $adminRedirect) {
    global $db; $adminGuard(); $assetid=(int)($_POST['assetid']??0); $asset=$db->table('assets')->where('id',$assetid)->first(); if(!$asset) $adminRedirect(null,'Asset not found.');
    $db->table('ownedassets')->where('assetid',$assetid)->delete(); $db->table('thumbnails')->where('assetid',$assetid)->delete(); $path=dirname(__DIR__).'/storage/assets/'.basename($asset->fileid); if(is_file($path)) @unlink($path); $db->table('assets')->where('id',$assetid)->delete();
    $adminRedirect('Deleted asset #'.$assetid.'.');
});

$router->get('/{slug}-item', function($thing){
    $router = new Routing();

    if(isset($_GET["id"])){
        $id = (int)$_GET["id"];
        global $db;

        $asset = $db->table("assets")->where("id", $id)->first();

        if($asset !== null){
            $page = new pagebuilder;
            $page::get_template("item", array("asset"=>$asset));
        } else {
            die($router->return_status(404));
        }

    } else {
        die($router->return_status(404));
    }

});

$router->get('/Upgrades/BuildersClubMemberships.aspx', function() {
    $page = new pagebuilder;
    $page::get_template("bc");
});

$router->get("/games", function() {
    $page = new pagebuilder;
    $page::get_template("games");
});

$router->get('/newlogin', function() {
   $page = new pagebuilder;
   $page::get_template("login/newlogin");
});

$router->get("/catalog", function() {
   $page = new pagebuilder;
   $page::get_template("catalog");
});

$router->get("/Login/iFrameLogin.aspx", function() {
    $page = new pagebuilder;
    $page::get_template("login/iframelogin");
});

$router->get('/games/{id}/{urlfriendly}', function($id, $urlfriendly){
    $page = new pagebuilder;
    $page::get_template("universe", ["id"=>$id]);
});

$router->get("/upgrades/robux", function() {
    $page = new pagebuilder;
    $page::get_template("upgrades/robux");
});

$router->get("/develop", function() {
    $page = new pagebuilder;
    $page::get_template("develop");
});

$router->get("/games/moreresultscached", function() {
    $page = new pagebuilder;
    $page::get_template("results");
});

$router->get("/home", function() {
    $page = new pagebuilder;
    $page::get_template("new_home");
});

$router->post('/my/character.aspx', function(){
    $pagebuilder = new pagebuilder();
    $pagebuilder->build_component("avatar_item", ["assetid"=>5, "action"=>"Wear"]);
});

$router->get('/thumbnail/user-avatar', function(){ 
    http_response_code(500);
    die();
});

$router->get("/my/account", function() {
    $page = new pagebuilder;
    $page::get_template("my/account");
});

$router->get("/my/character.aspx", function() {
    $page = new pagebuilder;
    $page::get_template("avatar");
});

$router->get("/my/groups.aspx", function() {
    $page = new pagebuilder;
    $page::get_template("groups/default");
});

$router->get("/my/money.aspx", function() {
    $page = new pagebuilder;
    $page::get_template("my/money");
});

$router->get('/Thumbs/Place.aspx', function(){
    header("Location: /images/9a4a5d6f14dd9785c0af4175e7aff706.png");
});

$router->get("/userads/{num}", function($num) {
    $sanitize = new sanitize();
    $num = sanitize::integer($num);
    
    if($num > 3 || $num < 1){
        global $router;
        $router->return_status(404);
    } else {
        $page = new pagebuilder;
        $page::get_template("userads/$num");
    }
    
});

$router->get("/CSS/Base/CSS/FetchCSS", function() {
    // var_dump($_GET);
    if(!isset($_GET["path"])){
        global $router;
        $router->return_status(404);
    } else {
        $path = $_GET["path"];
    }
    
    $sanitize = new sanitize();
    $path = $sanitize::get($path);
    
    if(file_exists("../storage/css/" . $path)){
        $css = file_get_contents("../storage/css/" . $path);
        header("Content-type: text/css");
        echo $css;
        die();
        
    } else {
        global $router;
        $router->return_status(404);
    }
    
});

$router->group('/group', function($router) {
    
    $router->get("/hi", function () {
        echo "test<br>";
    });
    
}, 'checkhelp');