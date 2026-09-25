<?php
use watrlabs\authentication;

global $db;
$auth = new authentication();
$auth->requiresession();
$admin = $auth->getuserinfo();
if (!$admin || (int)$admin->is_admin !== 1) { http_response_code(403); die('403 - Admin access required.'); }

$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$q = trim($_GET['q'] ?? '');

$usersQuery = $db->table('users')->orderBy('id', 'ASC');
if ($q !== '') {
    $qLike = '%' . $q . '%';
    $usersQuery = $usersQuery->where(function($query) use ($qLike) {
        $query->where('username', 'LIKE', $qLike)->orWhere('id', 'LIKE', $qLike);
    });
}
$users = $usersQuery->get();
$allUsers = $db->table('users')->orderBy('username', 'ASC')->get();
$assets = $db->table('assets')->orderBy('id', 'DESC')->limit(50)->get();
$assetCount = $db->table('assets')->count();
$userCount = $db->table('users')->count();
$messageCount = $db->table('messages')->count();
$ownedCount = $db->table('ownedassets')->count();
$admins = $db->table('users')->where('is_admin', 1)->count();
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>WatrBX Admin</title>
<style>
*{box-sizing:border-box}body{font:13px Arial,sans-serif;background:#eee;color:#222;margin:0}.wrap{max-width:1100px;margin:25px auto;background:#fff;border:1px solid #bbb;padding:20px;box-shadow:0 1px 4px #aaa}h1{font-size:24px;margin:0 0 5px}h2{font-size:17px;border-bottom:1px solid #ddd;padding-bottom:8px}.sub{color:#666;margin:0 0 18px}.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}.stat{border:1px solid #ccc;background:#f7f7f7;padding:12px}.stat b{display:block;font-size:20px;margin-top:4px}.card{border:1px solid #ccc;background:#f8f8f8;padding:15px;margin:14px 0}.row{display:flex;gap:10px}.row>div{flex:1}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}label{display:block;font-weight:bold;margin:9px 0 4px}input,textarea,select{width:100%;padding:7px;border:1px solid #aaa;background:#fff}textarea{height:80px;resize:vertical}.check{width:auto}.btn{margin-top:12px;background:#3b73a8;color:#fff;border:1px solid #28557f;padding:8px 14px;cursor:pointer}.danger{background:#9b3030;border-color:#722020}.green{background:#438044;border-color:#306030}.msg,.err{padding:9px;margin:10px 0}.msg{background:#e5f6e5;border:1px solid #8ac78a}.err{background:#ffe8e8;border:1px solid #d88}.tablewrap{overflow:auto;max-height:380px;background:#fff;border:1px solid #ddd}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:7px;border-bottom:1px solid #eee;white-space:nowrap}th{background:#eee}.muted{color:#777}.inline{display:inline}.inline .btn{margin:0}.search{display:flex;gap:8px}.search input{flex:1}.search button{width:auto}.full{grid-column:1/-1}@media(max-width:800px){.stats{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}.wrap{margin:0;border:0}.row{flex-direction:column}}
</style></head><body><div class="wrap">
<h1>WatrBX Admin Panel</h1><p class="sub">Logged in as <b><?=htmlspecialchars($admin->username)?></b> (ID <?=$admin->id?>)</p>
<?php if($message): ?><div class="msg"><?=$message?></div><?php endif; ?><?php if($error): ?><div class="err"><?=$error?></div><?php endif; ?>
<div class="stats"><div class="stat">Users<b><?=$userCount?></b></div><div class="stat">Admins<b><?=$admins?></b></div><div class="stat">Assets<b><?=$assetCount?></b></div><div class="stat">Owned items<b><?=$ownedCount?></b></div><div class="stat">Messages<b><?=$messageCount?></b></div></div>

<div class="grid">
<div class="card"><h2>Give currency</h2><form method="post" action="/admin/grant-currency"><label>User</label><select name="userid" required><?php foreach($allUsers as $u): ?><option value="<?=$u->id?>"><?=htmlspecialchars($u->username)?> (#<?=$u->id?>) - <?=$u->robux?> R$ / <?=$u->tix?> Tix</option><?php endforeach; ?></select><div class="row"><div><label>ROBux</label><input type="number" name="robux" value="0" min="0"></div><div><label>Tix</label><input type="number" name="tix" value="0" min="0"></div></div><button class="btn" type="submit">Give currency</button></form></div>
<div class="card"><h2>Set membership</h2><form method="post" action="/admin/set-membership"><label>User</label><select name="userid" required><?php foreach($allUsers as $u): ?><option value="<?=$u->id?>"><?=htmlspecialchars($u->username)?> (#<?=$u->id?>) - <?=htmlspecialchars($u->membership)?></option><?php endforeach; ?></select><label>Membership</label><select name="membership"><option>None</option><option>BuildersClub</option><option>TurboBuildersClub</option><option>OutrageousBuildersClub</option></select><button class="btn" type="submit">Set membership</button></form></div>
<div class="card"><h2>Admin permissions</h2><form method="post" action="/admin/set-admin"><label>User</label><select name="userid" required><?php foreach($allUsers as $u): ?><option value="<?=$u->id?>"><?=htmlspecialchars($u->username)?> (#<?=$u->id?>) - admin <?=$u->is_admin?'yes':'no'?></option><?php endforeach; ?></select><label>Permission</label><select name="is_admin"><option value="1">Administrator</option><option value="0">Regular user</option></select><button class="btn" type="submit">Update permission</button></form></div>
<div class="card"><h2>Grant catalog item</h2><form method="post" action="/admin/grant-asset"><label>User</label><select name="userid" required><?php foreach($allUsers as $u): ?><option value="<?=$u->id?>"><?=htmlspecialchars($u->username)?> (#<?=$u->id?>)</option><?php endforeach; ?></select><label>Asset ID</label><input type="number" name="assetid" min="1" required><button class="btn green" type="submit">Grant item</button></form></div>
<div class="card"><h2>Moderation</h2><form method="post" action="/admin/moderate"><label>User</label><select name="userid" required><?php foreach($allUsers as $u): ?><option value="<?=$u->id?>"><?=htmlspecialchars($u->username)?> (#<?=$u->id?>)</option><?php endforeach; ?></select><div class="row"><div><label>Action</label><select name="action"><option value="warning">Warning</option><option value="ban">Ban</option></select></div><div><label>Duration (days, ban only)</label><input type="number" name="days" value="1" min="1" max="3650"></div></div><label>Moderator note</label><textarea name="note" maxlength="255" required></textarea><button class="btn danger" type="submit">Apply moderation</button></form></div>
<div class="card"><h2>Send message</h2><form method="post" action="/admin/send-message"><label>Recipient</label><select name="userid" required><?php foreach($allUsers as $u): ?><option value="<?=$u->id?>"><?=htmlspecialchars($u->username)?> (#<?=$u->id?>)</option><?php endforeach; ?></select><label>Subject</label><input name="subject" maxlength="255" required><label>Body</label><textarea name="body" required></textarea><button class="btn" type="submit">Send message</button></form></div>
<div class="card full"><h2>Create catalog item</h2><form method="post" action="/admin/create-asset" enctype="multipart/form-data"><label>Name</label><input name="name" maxlength="200" required><label>Description</label><textarea name="description"></textarea><div class="row"><div><label>Category</label><input type="number" name="prodcategory" value="2" min="0"></div><div><label>ROBux price</label><input type="number" name="robux" value="0" min="0"></div><div><label>Tix price</label><input type="number" name="tix" value="0" min="0"></div><div><label>Remaining</label><input type="number" name="remaining" value="0" min="0"></div></div><div class="row"><div><label>Asset file</label><input type="file" name="asset"></div><div><label>Thumbnail</label><input type="file" name="thumbnail" accept="image/png,image/jpeg,image/webp"></div></div><label><input class="check" type="checkbox" name="featured"> Featured</label><label><input class="check" type="checkbox" name="limited"> Limited</label><label><input class="check" type="checkbox" name="limitedu"> Limited Unique</label><button class="btn" type="submit">Create catalog item</button></form></div>
</div>

<div class="card"><h2>User manager</h2><form class="search" method="get" action="/admin"><input name="q" value="<?=htmlspecialchars($q)?>" placeholder="Search username or ID"><button class="btn" type="submit">Search</button></form><div class="tablewrap" style="margin-top:10px"><table><tr><th>ID</th><th>Username</th><th>R$</th><th>Tix</th><th>Membership</th><th>Admin</th><th>Actions</th></tr><?php foreach($users as $u): ?><tr><td><?=$u->id?></td><td><?=htmlspecialchars($u->username)?></td><td><?=$u->robux?></td><td><?=$u->tix?></td><td><?=htmlspecialchars($u->membership)?></td><td><?=$u->is_admin?'yes':'no'?></td><td><form class="inline" method="post" action="/admin/delete-user" onsubmit="return confirm('Delete this user and their owned items?');"><input type="hidden" name="userid" value="<?=$u->id?>"><button class="btn danger" type="submit">Delete</button></form></td></tr><?php endforeach; ?></table></div></div>
<div class="card"><h2>Recent catalog assets</h2><div class="tablewrap"><table><tr><th>ID</th><th>Name</th><th>Owner</th><th>R$</th><th>Tix</th><th>Sales</th><th>Flags</th><th>Actions</th></tr><?php foreach($assets as $a): ?><tr><td><?=$a->id?></td><td><?=htmlspecialchars($a->name)?></td><td><?=$a->owner?></td><td><?=$a->robux?></td><td><?=$a->tix?></td><td><?=$a->sales?></td><td><?=($a->featured?'Featured ':'').($a->limited?'Limited ':'').($a->limitedu?'LU':'')?></td><td><form class="inline" method="post" action="/admin/delete-asset" onsubmit="return confirm('Delete this catalog asset?');"><input type="hidden" name="assetid" value="<?=$a->id?>"><button class="btn danger" type="submit">Delete</button></form></td></tr><?php endforeach; ?></table></div></div>
</div></body></html>
