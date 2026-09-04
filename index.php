<?php
ob_start();

// ========== CONFIG ==========
$botToken = getenv("BOT_TOKEN") ?: "8764046947:AAHMS_PJpA8NCDWZ_unABge_TwYA6RnCsok";
$website = "https://api.telegram.org/bot".$botToken;
$adminID = getenv("ADMIN_ID") ?: "5997885135";

// ========== FILES ==========
foreach(["balances.json","orders.json","temp.json","users.json","settings.json","products.json","payments.json"] as $f) {
    if(!file_exists($f)) file_put_contents($f, "{}");
}

// ========== DEFAULT SETTINGS ==========
$settings = json_decode(file_get_contents("settings.json"), true);
if(!isset($settings['proof_link'])) $settings['proof_link'] = "https://t.me/BMWytxv1";
if(!isset($settings['howto_link'])) $settings['howto_link'] = "https://t.me/BMWytxv1/145";
if(!isset($settings['support_user'])) $settings['support_user'] = "@BMWytx9";
if(!isset($settings['api_key'])) $settings['api_key'] = getenv("FAMGATEWAY_API_KEY") ?: "fam_6ce10996ccd42211d2a9a8e9aa0a4caed8b8f5ee";
file_put_contents("settings.json", json_encode($settings));

// ========== GET UPDATE ==========
$update = json_decode(file_get_contents("php://input"), true);

if(isset($update["callback_query"])){
    $chat_id = $update["callback_query"]["message"]["chat"]["id"];
    $message_id = $update["callback_query"]["message"]["message_id"];
    $first_name = $update["callback_query"]["from"]["first_name"] ?? "User";
    $username = $update["callback_query"]["from"]["username"] ?? "";
    $user_id = $update["callback_query"]["from"]["id"];
    $text = "";
    $data = $update["callback_query"]["data"] ?? "";
} else {
    $chat_id = $update["message"]["chat"]["id"] ?? 0;
    $message_id = $update["message"]["message_id"] ?? 0;
    $first_name = $update["message"]["chat"]["first_name"] ?? "User";
    $username = $update["message"]["chat"]["username"] ?? "";
    $user_id = $update["message"]["chat"]["id"] ?? 0;
    $text = $update["message"]["text"] ?? "";
    $data = "";
}

// ========== LOAD DATA ==========
$balances = json_decode(file_get_contents("balances.json"), true);
$users = json_decode(file_get_contents("users.json"), true);
$orders = json_decode(file_get_contents("orders.json"), true);
$products = json_decode(file_get_contents("products.json"), true);
$payments = json_decode(file_get_contents("payments.json"), true);

if(!isset($balances[$user_id])) $balances[$user_id] = 0;
if(!isset($users[$user_id])){
    $users[$user_id] = ["name" => $first_name, "username" => $username, "join" => date("d M Y")];
    file_put_contents("users.json", json_encode($users));
}

// ========== /START ==========
if($text == "/start"){
    saveBalance($user_id, $balances[$user_id]);
    sendMainMenu($chat_id, $first_name, $balances[$user_id]);
}

// ========== /ADMIN ==========
else if($text == "/admin"){
    if($user_id != $adminID){ sendMessage($chat_id, "❌Only admin"); return; }
    sendAdminPanel($chat_id, 0);
}

// ========== BUTTON CLICKS ==========
else if($data){
    answerCallback($update["callback_query"]["id"]);

    // ---- USER BUTTONS ----
    if($data == "addbal") sendAddBalance($chat_id, $message_id, $user_id);
    else if($data == "profile") sendProfile($chat_id, $message_id, $user_id);
    else if($data == "orders") sendOrders($chat_id, $message_id, $user_id);
    else if($data == "shop") sendAllProducts($chat_id, $message_id);
    else if($data == "proof") sendProof($chat_id, $message_id);
    else if($data == "howto") sendHowTo($chat_id, $message_id);
    else if($data == "support") sendSupport($chat_id, $message_id);
    
    // ---- BUY PRODUCT (SHOW PLANS) ----
    else if(strpos($data, "buy_") === 0){
        $pid = str_replace("buy_","",$data);
        sendProductPlans($chat_id, $message_id, $pid);
    }
    
    // ---- BUY SPECIFIC PLAN ----
    else if(strpos($data, "plan_") === 0){
        $parts = explode("_", $data);
        $pid = $parts[1];
        $plan_index = (int)$parts[2];
        buyPlan($chat_id, $message_id, $user_id, $pid, $plan_index);
    }
    
    // ============================================================
    // ================ ADMIN PANEL BUTTONS =======================
    // ============================================================
    
    // ---- 1. ADD PRODUCT ----
    else if($data == "addprod" && $user_id == $adminID){
        saveTemp($user_id, "waiting", "prod_name");
        editMsg($chat_id, $message_id, "📦 <b>Product Ka Naam Bhejo</b>\n\nExample: SILENT CHEATS SAFE", btn([[["⬅️ Cancel","backadmin"]]]));
    }
    
    // ---- 2. ADD PLAN ----
    else if($data == "addplan" && $user_id == $adminID){
        sendSelectProductForAddPlan($chat_id, $message_id);
    }
    
    else if(strpos($data, "addplanprod_") === 0){
        $pid = str_replace("addplanprod_","",$data);
        saveTemp($user_id, "addplan_pid", $pid);
        saveTemp($user_id, "waiting", "plan_days");
        editMsg($chat_id, $message_id, "📅 <b>Kitne Days ka plan hai?</b>\n\nExample: 1, 3, 7, 30", btn([[["⬅️ Cancel","backadmin"]]]));
    }
    
    // ---- 3. ADD PLAN KEYS ----
    else if($data == "addplankeys" && $user_id == $adminID){
        sendSelectProductForAddPlanKeys($chat_id, $message_id);
    }
    
    else if(strpos($data, "addkeysprod_") === 0){
        $pid = str_replace("addkeysprod_","",$data);
        sendPlansForAddKeys($chat_id, $message_id, $pid);
    }
    
    else if(strpos($data, "addkeysplan_") === 0){
        $parts = explode("_", $data);
        $pid = $parts[1];
        $plan_index = (int)$parts[2];
        saveTemp($user_id, "addkeys_pid", $pid);
        saveTemp($user_id, "addkeys_plan_index", $plan_index);
        saveTemp($user_id, "waiting", "add_plan_keys");
        editMsg($chat_id, $message_id, "🔑 <b>Is plan ke liye Keys Add Karo</b>\n\nEk line me ek key\n\nExample:\nKEY1-ABCD\nKEY2-EFGH\nKEY3-IJKL", btn([[["⬅️ Cancel","backadmin"]]]));
    }
    
    // ---- 4. EDIT PLAN ----
    else if($data == "editplan" && $user_id == $adminID){
        sendSelectProductForEditPlan($chat_id, $message_id);
    }
    
    else if(strpos($data, "editplanprod_") === 0){
        $pid = str_replace("editplanprod_","",$data);
        sendPlansForEdit($chat_id, $message_id, $pid);
    }
    
    else if(strpos($data, "editplan_") === 0){
        $parts = explode("_", $data);
        $pid = $parts[1];
        $plan_index = (int)$parts[2];
        saveTemp($user_id, "edit_pid", $pid);
        saveTemp($user_id, "edit_plan_index", $plan_index);
        $products = json_decode(file_get_contents("products.json"), true);
        $plan = $products[$pid]['plans'][$plan_index];
        $keys_count = isset($plan['keys']) ? count($plan['keys']) : 0;
        $msg = "✏️ <b>Edit Plan</b>\n\nCurrent: {$plan['days']} Days - ₹{$plan['price']}\nKeys: $keys_count\n\nKya edit karna hai?";
        $kb = [
            [["📅 Days","editplandays_{$pid}_{$plan_index}"]],
            [["💰 Price","editplanprice_{$pid}_{$plan_index}"]],
            [["🔑 Add Keys","editplankeys_{$pid}_{$plan_index}"]],
            [["⬅️ Back","backadmin"]]
        ];
        editMsg($chat_id, $message_id, $msg, btn($kb));
    }
    
    else if(strpos($data, "editplandays_") === 0){
        $parts = explode("_", $data);
        $pid = $parts[1];
        $plan_index = (int)$parts[2];
        saveTemp($user_id, "edit_pid", $pid);
        saveTemp($user_id, "edit_plan_index", $plan_index);
        saveTemp($user_id, "waiting", "edit_plan_days");
        editMsg($chat_id, $message_id, "📅 <b>Naya Days Bhejo</b>", btn([[["⬅️ Cancel","backadmin"]]]));
    }
    
    else if(strpos($data, "editplanprice_") === 0){
        $parts = explode("_", $data);
        $pid = $parts[1];
        $plan_index = (int)$parts[2];
        saveTemp($user_id, "edit_pid", $pid);
        saveTemp($user_id, "edit_plan_index", $plan_index);
        saveTemp($user_id, "waiting", "edit_plan_price");
        editMsg($chat_id, $message_id, "💰 <b>Naya Price Bhejo</b>\n\nExample: 199", btn([[["⬅️ Cancel","backadmin"]]]));
    }
    
    else if(strpos($data, "editplankeys_") === 0){
        $parts = explode("_", $data);
        $pid = $parts[1];
        $plan_index = (int)$parts[2];
        saveTemp($user_id, "edit_pid", $pid);
        saveTemp($user_id, "edit_plan_index", $plan_index);
        saveTemp($user_id, "waiting", "edit_plan_keys");
        editMsg($chat_id, $message_id, "🔑 <b>Is plan ke liye Keys Add Karo</b>\n\nEk line me ek key\n\nExample:\nKEY1-ABCD\nKEY2-EFGH", btn([[["⬅️ Cancel","backadmin"]]]));
    }
    
    // ---- 5. DELETE PLAN ----
    else if($data == "delplan" && $user_id == $adminID){
        sendSelectProductForDeletePlan($chat_id, $message_id);
    }
    
    else if(strpos($data, "delplanprod_") === 0){
        $pid = str_replace("delplanprod_","",$data);
        sendPlansForDelete($chat_id, $message_id, $pid);
    }
    
    else if(strpos($data, "delplan_") === 0){
        $parts = explode("_", $data);
        $pid = $parts[1];
        $plan_index = (int)$parts[2];
        $products = json_decode(file_get_contents("products.json"), true);
        $plan = $products[$pid]['plans'][$plan_index];
        unset($products[$pid]['plans'][$plan_index]);
        $products[$pid]['plans'] = array_values($products[$pid]['plans']);
        file_put_contents("products.json", json_encode($products));
        editMsg($chat_id, $message_id, "🗑️ <b>Plan Delete Ho Gaya:</b> {$plan['days']} Days - ₹{$plan['price']}", btn([[["⬅️ Back","backadmin"]]]));
    }
    
    // ---- 6. EDIT PRODUCT ----
    else if($data == "editprod" && $user_id == $adminID){
        sendSelectProductForEdit($chat_id, $message_id);
    }
    
    else if(strpos($data, "editprod_") === 0){
        $pid = str_replace("editprod_","",$data);
        saveTemp($user_id, "edit_pid", $pid);
        $products = json_decode(file_get_contents("products.json"), true);
        $p = $products[$pid];
        $msg = "✏️ <b>Edit Product</b>\n\nName: {$p['name']}\nPlans: ".count($p['plans']);
        $kb = [
            [["📝 Name","edit_name_$pid"]],
            [["⬅️ Back","backadmin"]]
        ];
        editMsg($chat_id, $message_id, $msg, btn($kb));
    }
    
    else if(strpos($data, "edit_name_") === 0){ 
        $pid = str_replace("edit_name_","",$data); 
        saveTemp($user_id, "edit_pid", $pid); 
        saveTemp($user_id, "waiting", "edit_name"); 
        editMsg($chat_id, $message_id, "📝 <b>Naya Name Bhejo</b>", btn([[["⬅️ Cancel","backadmin"]]])); 
    }
    
    // ---- 7. DELETE PRODUCT ----
    else if($data == "delprod" && $user_id == $adminID){
        sendSelectProductForDelete($chat_id, $message_id);
    }
    
    else if(strpos($data, "delprod_") === 0){
        $pid = str_replace("delprod_","",$data);
        $products = json_decode(file_get_contents("products.json"), true);
        if(!isset($products[$pid])){
            editMsg($chat_id, $message_id, "❌ Product nahi mila!", btn([[["⬅️ Back","backadmin"]]]));
            return;
        }
        $p = $products[$pid];
        unset($products[$pid]);
        file_put_contents("products.json", json_encode($products));
        editMsg($chat_id, $message_id, "🗑️ <b>Product Delete Ho Gaya!</b>\n\nName: {$p['name']}\nPlans: ".count($p['plans']), btn([[["⬅️ Back to Admin","backadmin"]]]));
    }
    
    // ---- 8. BROADCAST ----
    else if($data == "broadcast" && $user_id == $adminID){
        saveTemp($user_id, "waiting", "broadcast_text");
        editMsg($chat_id, $message_id, "📢 <b>Broadcast Message</b>\n\nMessage bhejo (Text, Photo, Video, Voice, Document)\n\n<b>Note:</b> Photo/Video/Voice/Document ke saath caption bhi bhej sakte ho", btn([[["⬅️ Cancel","backadmin"]]]));
    }
    
    // ---- 9. USER LIST ----
    else if($data == "userlist" && $user_id == $adminID){
        $users = json_decode(file_get_contents("users.json"), true);
        $msg = "👥 <b>Total Users:</b> ".count($users)."\n\n";
        $i = 1;
        foreach($users as $id => $u){
            $msg .= "$i. {$u['name']}";
            if(!empty($u['username'])) $msg .= " (@{$u['username']})";
            $msg .= "\n   🆔 <code>$id</code>\n   📅 {$u['join']}\n\n";
            $i++;
            if($i > 20) { $msg .= "\n... aur ". (count($users)-20) ." users"; break; }
        }
        $kb = [[["⬅️ Back","backadmin"]]];
        editMsg($chat_id, $message_id, $msg, btn($kb));
    }
    
    // ---- 10. ADD USER BALANCE ----
    else if($data == "adduserbal" && $user_id == $adminID){
        saveTemp($user_id, "waiting", "adduserbal_id");
        editMsg($chat_id, $message_id, "💰 <b>Add Balance to User</b>\n\nUser ID bhejo (jo profile me dikhta hai)\n\nExample: 8154859186", btn([[["⬅️ Cancel","backadmin"]]]));
    }
    
    // ---- 11. PROOF LINK ----
    else if($data == "setproof" && $user_id == $adminID){ 
        saveTemp($user_id, "waiting", "proof"); 
        editMsg($chat_id, $message_id, "📄 <b>Payment Proof Link Bhejo</b>", btn([[["⬅️ Cancel","backadmin"]]])); 
    }
    
    // ---- 12. HOWTO LINK ----
    else if($data == "sethowto" && $user_id == $adminID){ 
        saveTemp($user_id, "waiting", "howto"); 
        editMsg($chat_id, $message_id, "📖 <b>How To Use Link Bhejo</b>", btn([[["⬅️ Cancel","backadmin"]]])); 
    }
    
    // ---- 13. SUPPORT USERNAME ----
    else if($data == "setsupport" && $user_id == $adminID){ 
        saveTemp($user_id, "waiting", "support"); 
        editMsg($chat_id, $message_id, "💬 <b>Support Username Bhejo</b>", btn([[["⬅️ Cancel","backadmin"]]])); 
    }
    
    // ---- 14. API KEY ----
    else if($data == "setapi" && $user_id == $adminID){ 
        saveTemp($user_id, "waiting", "api"); 
        editMsg($chat_id, $message_id, "🔑 <b>FamPay API Key Bhejo</b>\n\nExample: FAM_FCAA374ADAFD806DCD3AA33A29DBE6AFBB27A09A", btn([[["⬅️ Cancel","backadmin"]]])); 
    }
    
    // ---- 15. BACK TO ADMIN ----
    else if($data == "backadmin"){
        sendAdminPanel($chat_id, $message_id);
    }
    
    // ---- PAYMENT ----
    else if(strpos($data, "pay_") === 0){ 
        $amount = str_replace("pay_","",$data); 
        createFamPayOrder($chat_id, $message_id, $user_id, $amount); 
    }
    else if($data == "custom"){ 
        saveTemp($user_id, "amount", "0"); 
        sendKeypad($chat_id, $message_id, $user_id, "0"); 
    }
    else if(strpos($data, "key_") === 0){ 
        handleKeypad($chat_id, $message_id, $user_id, str_replace("key_","",$data)); 
    }
    else if(strpos($data, "confirm_") === 0){ 
        $amount = str_replace("confirm_","",$data); 
        createFamPayOrder($chat_id, $message_id, $user_id, $amount); 
    }
    else if(strpos($data, "check_") === 0){ 
        checkPayment($chat_id, $message_id, str_replace("check_","",$data), $user_id); 
    }
    else if(strpos($data, "cancel_") === 0){ 
        cancelOrder($chat_id, $message_id, str_replace("cancel_","",$data), $user_id); 
    }
    
    // ---- NAVIGATION ----
    else if($data == "back") sendMainMenu($chat_id, $first_name, $balances[$user_id], $message_id);
    else if($data == "backkey") sendAddBalance($chat_id, $message_id, $user_id);
    else if($data == "backshop") sendAllProducts($chat_id, $message_id);
}

// ========== TEXT MESSAGES ==========
else if($text && $text != "/start" && $text != "/admin"){
    $temp = json_decode(file_get_contents("temp.json"), true);
    $waiting = $temp[$user_id]['waiting'] ?? "";
    $settings = json_decode(file_get_contents("settings.json"), true);
    $products = json_decode(file_get_contents("products.json"), true);

    // ---- ADD PRODUCT NAME ----
    if($waiting == "prod_name" && $user_id == $adminID){
        $pname = $text;
        $pid = "p".time();
        
        $products[$pid] = [
            "name" => $pname,
            "plans" => []
        ];
        file_put_contents("products.json", json_encode($products));
        
        $temp[$user_id]['waiting'] = "";
        $temp[$user_id]['prod_name'] = "";
        file_put_contents("temp.json", json_encode($temp));
        
        sendMessage($chat_id, "✅ Product Add ho gaya!\n\nName: $pname\n\nAb /admin se '➕ Add Plan' karke plans add karo.");
        sendAdminPanel($chat_id, 0);
    }
    
    // ---- ADD PLAN DAYS ----
    else if($waiting == "plan_days" && $user_id == $adminID){
        $temp[$user_id]['plan_days'] = (int)$text;
        $temp[$user_id]['waiting'] = "plan_price";
        file_put_contents("temp.json", json_encode($temp));
        sendMessage($chat_id, "💰 <b>Is plan ka Price kya hai?</b>\n\nExample: 90");
    }
    
    // ---- ADD PLAN PRICE ----
    else if($waiting == "plan_price" && $user_id == $adminID){
        $pid = $temp[$user_id]['addplan_pid'];
        $days = $temp[$user_id]['plan_days'];
        $price = (int)$text;
        
        $products = json_decode(file_get_contents("products.json"), true);
        $products[$pid]['plans'][] = [
            "days" => $days, 
            "price" => $price,
            "keys" => []
        ];
        file_put_contents("products.json", json_encode($products));
        
        $temp[$user_id]['waiting'] = "";
        $temp[$user_id]['plan_days'] = "";
        $temp[$user_id]['addplan_pid'] = "";
        file_put_contents("temp.json", json_encode($temp));
        
        sendMessage($chat_id, "✅ Plan Add Ho Gaya!\n\nDays: $days\nPrice: ₹$price\n\nAb is plan ke liye keys add karo!\n/admin se '🔑 Add Plan Keys' select karo.");
        sendAdminPanel($chat_id, 0);
    }
    
    // ---- ADD PLAN KEYS ----
    else if($waiting == "add_plan_keys" && $user_id == $adminID){
        $keys = array_filter(array_map('trim', explode("\n", $text)));
        if(count($keys) == 0){
            sendMessage($chat_id, "❌ Koi key nahi mili. Dubara keys bhejo");
            return;
        }
        
        $pid = $temp[$user_id]['addkeys_pid'];
        $plan_index = $temp[$user_id]['addkeys_plan_index'];
        
        $products = json_decode(file_get_contents("products.json"), true);
        
        if(!isset($products[$pid]['plans'][$plan_index]['keys'])) {
            $products[$pid]['plans'][$plan_index]['keys'] = [];
        }
        $products[$pid]['plans'][$plan_index]['keys'] = array_merge(
            $products[$pid]['plans'][$plan_index]['keys'], 
            $keys
        );
        file_put_contents("products.json", json_encode($products));
        
        $temp[$user_id]['waiting'] = "";
        $temp[$user_id]['addkeys_pid'] = "";
        $temp[$user_id]['addkeys_plan_index'] = "";
        file_put_contents("temp.json", json_encode($temp));
        
        $plan = $products[$pid]['plans'][$plan_index];
        sendMessage($chat_id, "✅ ".count($keys)." Keys Add Ho Gayi!\n\nPlan: {$plan['days']} Days\nTotal Keys: ".count($plan['keys']));
        sendAdminPanel($chat_id, 0);
    }
    
    // ---- EDIT NAME ----
    else if($waiting == "edit_name" && $user_id == $adminID){
        $pid = $temp[$user_id]['edit_pid'];
        $products[$pid]['name'] = $text;
        file_put_contents("products.json", json_encode($products));
        $temp[$user_id]['waiting'] = "";
        file_put_contents("temp.json", json_encode($temp));
        sendMessage($chat_id, "✅ Name Update: $text");
        sendAdminPanel($chat_id, 0);
    }
    
    // ---- EDIT PLAN DAYS ----
    else if($waiting == "edit_plan_days" && $user_id == $adminID){
        $pid = $temp[$user_id]['edit_pid'];
        $plan_index = $temp[$user_id]['edit_plan_index'];
        $products = json_decode(file_get_contents("products.json"), true);
        $products[$pid]['plans'][$plan_index]['days'] = (int)$text;
        file_put_contents("products.json", json_encode($products));
        $temp[$user_id]['waiting'] = "";
        file_put_contents("temp.json", json_encode($temp));
        sendMessage($chat_id, "✅ Days Update: $text");
        sendAdminPanel($chat_id, 0);
    }
    
    // ---- EDIT PLAN PRICE ----
    else if($waiting == "edit_plan_price" && $user_id == $adminID){
        $pid = $temp[$user_id]['edit_pid'];
        $plan_index = $temp[$user_id]['edit_plan_index'];
        $products = json_decode(file_get_contents("products.json"), true);
        $products[$pid]['plans'][$plan_index]['price'] = (int)$text;
        file_put_contents("products.json", json_encode($products));
        $temp[$user_id]['waiting'] = "";
        file_put_contents("temp.json", json_encode($temp));
        sendMessage($chat_id, "✅ Price Update: ₹$text");
        sendAdminPanel($chat_id, 0);
    }
    
    // ---- EDIT PLAN KEYS ----
    else if($waiting == "edit_plan_keys" && $user_id == $adminID){
        $keys = array_filter(array_map('trim', explode("\n", $text)));
        if(count($keys) == 0){
            sendMessage($chat_id, "❌ Koi key nahi mili. Dubara keys bhejo");
            return;
        }
        
        $pid = $temp[$user_id]['edit_pid'];
        $plan_index = $temp[$user_id]['edit_plan_index'];
        
        $products = json_decode(file_get_contents("products.json"), true);
        
        if(!isset($products[$pid]['plans'][$plan_index]['keys'])) {
            $products[$pid]['plans'][$plan_index]['keys'] = [];
        }
        $products[$pid]['plans'][$plan_index]['keys'] = array_merge(
            $products[$pid]['plans'][$plan_index]['keys'], 
            $keys
        );
        file_put_contents("products.json", json_encode($products));
        
        $temp[$user_id]['waiting'] = "";
        file_put_contents("temp.json", json_encode($temp));
        
        $plan = $products[$pid]['plans'][$plan_index];
        sendMessage($chat_id, "✅ ".count($keys)." Keys Add Ho Gayi!\n\nPlan: {$plan['days']} Days\nTotal Keys: ".count($plan['keys']));
        sendAdminPanel($chat_id, 0);
    }
    
    // ---- BROADCAST TEXT ----
    else if($waiting == "broadcast_text" && $user_id == $adminID){
        $users = json_decode(file_get_contents("users.json"), true);
        $sent = 0;
        $failed = 0;
        
        foreach($users as $uid => $u){
            $result = sendMessage($uid, "📢 <b>Announcement</b>\n\n$text");
            if($result !== false) $sent++; else $failed++;
            usleep(50000);
        }
        
        $temp[$user_id]['waiting'] = "";
        file_put_contents("temp.json", json_encode($temp));
        
        sendMessage($chat_id, "✅ <b>Broadcast Complete!</b>\n\nTotal Users: ".count($users)."\n✅ Sent: $sent\n❌ Failed: $failed");
        sendAdminPanel($chat_id, 0);
    }
    
    // ---- ADD USER BALANCE - USER ID ----
    else if($waiting == "adduserbal_id" && $user_id == $adminID){
        $target_id = trim($text);
        $users = json_decode(file_get_contents("users.json"), true);
        
        if(!isset($users[$target_id])){
            sendMessage($chat_id, "❌ User ID <code>$target_id</code> nahi mila!\n\n/users se list dekh lo.", btn([[["⬅️ Back","backadmin"]]]));
            return;
        }
        
        $temp[$user_id]['adduserbal_target'] = $target_id;
        $temp[$user_id]['waiting'] = "adduserbal_amount";
        file_put_contents("temp.json", json_encode($temp));
        
        sendMessage($chat_id, "💰 <b>Add Balance to {$users[$target_id]['name']}</b>\n\nKitna amount add karna hai?\n\nExample: 100", btn([[["⬅️ Cancel","backadmin"]]]));
    }
    
    // ---- ADD USER BALANCE - AMOUNT ----
    else if($waiting == "adduserbal_amount" && $user_id == $adminID){
        $target_id = $temp[$user_id]['adduserbal_target'];
        $amount = (int)$text;
        
        if($amount <= 0){
            sendMessage($chat_id, "❌ Amount 0 se zyada hona chahiye!");
            return;
        }
        
        $balances = json_decode(file_get_contents("balances.json"), true);
        $users = json_decode(file_get_contents("users.json"), true);
        
        $old_bal = $balances[$target_id] ?? 0;
        $balances[$target_id] = $old_bal + $amount;
        file_put_contents("balances.json", json_encode($balances));
        
        $temp[$user_id]['waiting'] = "";
        $temp[$user_id]['adduserbal_target'] = "";
        file_put_contents("temp.json", json_encode($temp));
        
        sendMessage($chat_id, "✅ <b>Balance Added!</b>\n\nUser: {$users[$target_id]['name']}\n🆔 <code>$target_id</code>\n💰 Added: ₹$amount\n💲 New Balance: ₹".$balances[$target_id]);
        sendMessage($target_id, "💰 <b>Balance Added!</b>\n\n₹$amount aapke account me add kar diya gaya hai.\n💲 <b>New Balance: ₹".$balances[$target_id]."</b>");
        
        sendAdminPanel($chat_id, 0);
    }
    
    // ---- SETTINGS ----
    else if($waiting == "proof" && $user_id == $adminID){ 
        $settings['proof_link'] = $text; 
        file_put_contents("settings.json", json_encode($settings)); 
        $temp[$user_id]['waiting'] = ""; 
        file_put_contents("temp.json", json_encode($temp)); 
        sendMessage($chat_id, "✅ Payment Proof Link Update: $text"); 
        sendAdminPanel($chat_id, 0); 
    }
    else if($waiting == "howto" && $user_id == $adminID){ 
        $settings['howto_link'] = $text; 
        file_put_contents("settings.json", json_encode($settings)); 
        $temp[$user_id]['waiting'] = ""; 
        file_put_contents("temp.json", json_encode($temp)); 
        sendMessage($chat_id, "✅ How To Use Link Update: $text"); 
        sendAdminPanel($chat_id, 0); 
    }
    else if($waiting == "support" && $user_id == $adminID){ 
        $settings['support_user'] = $text; 
        file_put_contents("settings.json", json_encode($settings)); 
        $temp[$user_id]['waiting'] = ""; 
        file_put_contents("temp.json", json_encode($temp)); 
        sendMessage($chat_id, "✅ Support Username Update: $text"); 
        sendAdminPanel($chat_id, 0); 
    }
    else if($waiting == "api" && $user_id == $adminID){ 
        $settings['api_key'] = trim($text); 
        file_put_contents("settings.json", json_encode($settings)); 
        $temp[$user_id]['waiting'] = ""; 
        file_put_contents("temp.json", json_encode($temp)); 
        sendMessage($chat_id, "✅ API Key Update: ".substr($text,0,20)."..."); 
        sendAdminPanel($chat_id, 0); 
    }
    
    // ---- CUSTOM AMOUNT ----
    else if(isset($temp[$user_id]['amount'])){
        $amount = (int)$text;
        if($amount >= 1 && $amount <= 5000){
            createFamPayOrder($chat_id, 0, $user_id, $amount);
            $temp[$user_id]['amount'] = null;
            file_put_contents("temp.json", json_encode($temp));
        }else{
            sendMessage($chat_id, "❌ Amount ₹1 se ₹5000 ke beech me hona chahiye");
        }
    }
}

// ========== HANDLE MEDIA FOR BROADCAST ==========
else if(isset($update["message"]) && $user_id == $adminID){
    $temp = json_decode(file_get_contents("temp.json"), true);
    $waiting = $temp[$user_id]['waiting'] ?? "";
    
    if($waiting == "broadcast_text"){
        $caption = $update["message"]["caption"] ?? "";
        $users = json_decode(file_get_contents("users.json"), true);
        $sent = 0;
        $failed = 0;
        
        if(isset($update["message"]["photo"])){
            $file_id = $update["message"]["photo"][count($update["message"]["photo"])-1]["file_id"];
            foreach($users as $uid => $u){
                $result = sendPhoto($uid, $file_id, "📢 <b>Announcement</b>\n\n$caption");
                if($result !== false) $sent++; else $failed++;
                usleep(50000);
            }
        }
        else if(isset($update["message"]["video"])){
            $file_id = $update["message"]["video"]["file_id"];
            foreach($users as $uid => $u){
                $result = sendVideo($uid, $file_id, "📢 <b>Announcement</b>\n\n$caption");
                if($result !== false) $sent++; else $failed++;
                usleep(50000);
            }
        }
        else if(isset($update["message"]["voice"])){
            $file_id = $update["message"]["voice"]["file_id"];
            foreach($users as $uid => $u){
                $result = sendVoice($uid, $file_id, "📢 <b>Announcement</b>\n\n$caption");
                if($result !== false) $sent++; else $failed++;
                usleep(50000);
            }
        }
        else if(isset($update["message"]["document"])){
            $file_id = $update["message"]["document"]["file_id"];
            foreach($users as $uid => $u){
                $result = sendDocument($uid, $file_id, "📢 <b>Announcement</b>\n\n$caption");
                if($result !== false) $sent++; else $failed++;
                usleep(50000);
            }
        }
        
        $temp[$user_id]['waiting'] = "";
        file_put_contents("temp.json", json_encode($temp));
        
        sendMessage($chat_id, "✅ <b>Broadcast Complete!</b>\n\nTotal Users: ".count($users)."\n✅ Sent: $sent\n❌ Failed: $failed");
        sendAdminPanel($chat_id, 0);
    }
}

// ============================================================
// ======================== FUNCTIONS ==========================
// ============================================================

function sendAdminPanel($chat_id, $message_id = 0){
    $settings = json_decode(file_get_contents("settings.json"), true);
    $users = json_decode(file_get_contents("users.json"), true);
    $total_users = count($users);
    
    $msg = "👑 <b>Admin Panel</b>\n\n".
           "👥 Total Users: $total_users\n".
           "📄 Proof: {$settings['proof_link']}\n".
           "📖 HowTo: {$settings['howto_link']}\n".
           "💬 Support: {$settings['support_user']}\n".
           "🔑 API Key: ".substr($settings['api_key'],0,20)."...";
    $kb = [
        [["📦 Add Product","addprod"]],
        [["➕ Add Plan","addplan"]],
        [["🔑 Add Plan Keys","addplankeys"]],
        [["✏️ Edit Plan","editplan"]],
        [["🗑️ Delete Plan","delplan"]],
        [["✏️ Edit Product","editprod"]],
        [["🗑️ Delete Product","delprod"]],
        [["📢 Broadcast","broadcast"]],
        [["👥 User List","userlist"]],
        [["💰 Add User Balance","adduserbal"]],
        [["📄 Proof Link","setproof"]],
        [["📖 HowTo Link","sethowto"]],
        [["💬 Support Username","setsupport"]],
        [["🔑 API Key","setapi"]],
        [["⬅️ Back to Menu","back"]]
    ];
    if($message_id > 0) editMsg($chat_id, $message_id, $msg, btn($kb));
    else sendMessage($chat_id, $msg, btn($kb));
}

// ========== PRODUCT PLANS DISPLAY ==========
function sendProductPlans($chat_id, $message_id, $pid){
    $products = json_decode(file_get_contents("products.json"), true);
    
    if(!isset($products[$pid])){
        editMsg($chat_id, $message_id, "❌ Product nahi mila", btn([[["⬅️ Back","backshop"]]]));
        return;
    }
    
    $p = $products[$pid];
    $plans = $p['plans'] ?? [];
    
    if(count($plans) == 0){
        editMsg($chat_id, $message_id, "❌ <b>{$p['name']}</b>\n\nIs product ke liye koi plan nahi hai.\nAdmin se contact karo.", btn([[["⬅️ Back to Shop","backshop"]]]));
        return;
    }
    
    usort($plans, function($a, $b) { return $a['days'] - $b['days']; });
    
    $msg = "📦 <b>{$p['name']}</b>\n\n<b>Choose a plan</b>\n";
    $kb = [];
    
    foreach($plans as $index => $plan){
        $day_text = $plan['days'] . ($plan['days'] > 1 ? " Days" : " Day");
        $keys_count = isset($plan['keys']) ? count($plan['keys']) : 0;
        $msg .= "\n• {$day_text} — ₹{$plan['price']} (".$keys_count." keys)";
        $kb[] = [[$day_text . " - ₹{$plan['price']}", "plan_{$pid}_{$index}"]];
    }
    
    $kb[] = [["⬅️ Back to Shop","backshop"]];
    
    editMsg($chat_id, $message_id, $msg, btn($kb));
}

// ========== BUY PLAN ==========
function buyPlan($chat_id, $message_id, $user_id, $pid, $plan_index){
    $products = json_decode(file_get_contents("products.json"), true);
    $balances = json_decode(file_get_contents("balances.json"), true);
    $orders = json_decode(file_get_contents("orders.json"), true);

    if(!isset($products[$pid])){
        editMsg($chat_id, $message_id, "❌ Product nahi mila", btn([[["⬅️ Back","backshop"]]]));
        return;
    }
    
    $p = $products[$pid];
    $plans = $p['plans'] ?? [];
    
    if(!isset($plans[$plan_index])){
        editMsg($chat_id, $message_id, "❌ Plan nahi mila", btn([[["⬅️ Back","backshop"]]]));
        return;
    }
    
    $plan = $plans[$plan_index];
    $bal = $balances[$user_id] ?? 0;

    if($bal < $plan['price']){
        editMsg($chat_id, $message_id, "❌ <b>Insufficient Balance</b>\n\nPlan: {$plan['days']} Days\nPrice: ₹{$plan['price']}\nYour Balance: ₹$bal", btn([[["💰 Add Balance","addbal"]],[["⬅️ Back","backshop"]]]));
        return;
    }
    
    if(!isset($plan['keys']) || count($plan['keys']) == 0){
        editMsg($chat_id, $message_id, "❌ <b>Out of Stock!</b>\n\nIs plan ke liye koi key nahi hai.\nAdmin se contact karo.", btn([[["⬅️ Back","backshop"]]]));
        return;
    }

    $balances[$user_id] -= $plan['price'];
    file_put_contents("balances.json", json_encode($balances));

    $key = array_shift($plan['keys']);
    $products[$pid]['plans'][$plan_index]['keys'] = $plan['keys'];
    file_put_contents("products.json", json_encode($products));

    $order_id = "ORD".time().rand(100,999);
    $orders[$order_id] = [
        "user" => $user_id,
        "product_id" => $pid,
        "product_name" => $p['name'],
        "plan" => $plan,
        "price" => $plan['price'],
        "days" => $plan['days'],
        "status" => "Delivered",
        "date" => date("d M Y H:i"),
        "key" => $key
    ];
    file_put_contents("orders.json", json_encode($orders));

    $msg = "✅ <b>Order Delivered!</b>\n\nProduct: {$p['name']}\nPlan: {$plan['days']} Days\nPrice: ₹{$plan['price']}\nOrder ID: <code>$order_id</code>\nDate: ".date("d M Y H:i")."\n\n🔑 <b>Your Key:</b>\n<code>$key</code>";
    editMsg($chat_id, $message_id, $msg, btn([[["📦 My Orders","orders"]],[["⬅️ Back to Menu","back"]]]));
}

// ========== ADMIN SELECT FUNCTIONS ==========
function sendSelectProductForAddPlan($chat_id, $message_id){
    $products = json_decode(file_get_contents("products.json"), true);
    if(count($products) == 0){
        editMsg($chat_id, $message_id, "❌ <b>Koi Product nahi hai!</b>\n\nPehle '📦 Add Product' se product add karo.", btn([[["⬅️ Back","backadmin"]]]));
        return;
    }
    $msg = "➕ <b>Kis Product me Plan Add karna hai?</b>";
    $kb = [];
    foreach($products as $id => $p){
        $plan_count = isset($p['plans']) ? count($p['plans']) : 0;
        $kb[] = [["{$p['name']} (".$plan_count." plans)", "addplanprod_$id"]];
    }
    $kb[] = [["⬅️ Back","backadmin"]];
    editMsg($chat_id, $message_id, $msg, btn($kb));
}

function sendSelectProductForAddPlanKeys($chat_id, $message_id){
    $products = json_decode(file_get_contents("products.json"), true);
    if(count($products) == 0){
        editMsg($chat_id, $message_id, "❌ <b>Koi Product nahi hai!</b>", btn([[["⬅️ Back","backadmin"]]]));
        return;
    }
    $msg = "🔑 <b>Kis Product ke Plan me Keys Add karna hai?</b>";
    $kb = [];
    foreach($products as $id => $p){
        $plan_count = isset($p['plans']) ? count($p['plans']) : 0;
        $kb[] = [["{$p['name']} (".$plan_count." plans)", "addkeysprod_$id"]];
    }
    $kb[] = [["⬅️ Back","backadmin"]];
    editMsg($chat_id, $message_id, $msg, btn($kb));
}

function sendPlansForAddKeys($chat_id, $message_id, $pid){
    $products = json_decode(file_get_contents("products.json"), true);
    $p = $products[$pid];
    if(!isset($p['plans']) || count($p['plans']) == 0){
        editMsg($chat_id, $message_id, "❌ <b>Is product me koi plan nahi hai!</b>\n\nPehle '➕ Add Plan' se plan add karo.", btn([[["⬅️ Back","backadmin"]]]));
        return;
    }
    $msg = "🔑 <b>Add Keys - {$p['name']}</b>\n\nKis plan me keys add karna hai?";
    $kb = [];
    foreach($p['plans'] as $index => $plan){
        $keys_count = isset($plan['keys']) ? count($plan['keys']) : 0;
        $kb[] = [["{$plan['days']} Days - ₹{$plan['price']} (".$keys_count." keys)", "addkeysplan_{$pid}_{$index}"]];
    }
    $kb[] = [["⬅️ Back","backadmin"]];
    editMsg($chat_id, $message_id, $msg, btn($kb));
}

function sendSelectProductForEditPlan($chat_id, $message_id){
    $products = json_decode(file_get_contents("products.json"), true);
    if(count($products) == 0){
        editMsg($chat_id, $message_id, "❌ <b>Koi Product nahi hai!</b>", btn([[["⬅️ Back","backadmin"]]]));
        return;
    }
    $msg = "✏️ <b>Kis Product ka Plan Edit karna hai?</b>";
    $kb = [];
    foreach($products as $id => $p){
        $plan_count = isset($p['plans']) ? count($p['plans']) : 0;
        $kb[] = [["{$p['name']} (".$plan_count." plans)", "editplanprod_$id"]];
    }
    $kb[] = [["⬅️ Back","backadmin"]];
    editMsg($chat_id, $message_id, $msg, btn($kb));
}

function sendPlansForEdit($chat_id, $message_id, $pid){
    $products = json_decode(file_get_contents("products.json"), true);
    $p = $products[$pid];
    if(!isset($p['plans']) || count($p['plans']) == 0){
        editMsg($chat_id, $message_id, "❌ <b>Is product me koi plan nahi hai!</b>\n\nPehle '➕ Add Plan' se plan add karo.", btn([[["⬅️ Back","backadmin"]]]));
        return;
    }
    $msg = "✏️ <b>Edit Plan - {$p['name']}</b>\n\nKis plan ko edit karna hai?";
    $kb = [];
    foreach($p['plans'] as $index => $plan){
        $keys_count = isset($plan['keys']) ? count($plan['keys']) : 0;
        $kb[] = [["{$plan['days']} Days - ₹{$plan['price']} (".$keys_count." keys)", "editplan_{$pid}_{$index}"]];
    }
    $kb[] = [["⬅️ Back","backadmin"]];
    editMsg($chat_id, $message_id, $msg, btn($kb));
}

function sendSelectProductForDeletePlan($chat_id, $message_id){
    $products = json_decode(file_get_contents("products.json"), true);
    if(count($products) == 0){
        editMsg($chat_id, $message_id, "❌ <b>Koi Product nahi hai!</b>", btn([[["⬅️ Back","backadmin"]]]));
        return;
    }
    $msg = "🗑️ <b>Kis Product ka Plan Delete karna hai?</b>";
    $kb = [];
    foreach($products as $id => $p){
        $plan_count = isset($p['plans']) ? count($p['plans']) : 0;
        $kb[] = [["{$p['name']} (".$plan_count." plans)", "delplanprod_$id"]];
    }
    $kb[] = [["⬅️ Back","backadmin"]];
    editMsg($chat_id, $message_id, $msg, btn($kb));
}

function sendPlansForDelete($chat_id, $message_id, $pid){
    $products = json_decode(file_get_contents("products.json"), true);
    $p = $products[$pid];
    if(!isset($p['plans']) || count($p['plans']) == 0){
        editMsg($chat_id, $message_id, "❌ <b>Is product me koi plan nahi hai!</b>", btn([[["⬅️ Back","backadmin"]]]));
        return;
    }
    $msg = "🗑️ <b>Delete Plan - {$p['name']}</b>\n\nKis plan ko delete karna hai?";
    $kb = [];
    foreach($p['plans'] as $index => $plan){
        $kb[] = [["❌ {$plan['days']} Days - ₹{$plan['price']}", "delplan_{$pid}_{$index}"]];
    }
    $kb[] = [["⬅️ Back","backadmin"]];
    editMsg($chat_id, $message_id, $msg, btn($kb));
}

function sendSelectProductForDelete($chat_id, $message_id){
    $products = json_decode(file_get_contents("products.json"), true);
    if(count($products) == 0){
        editMsg($chat_id, $message_id, "❌ <b>Koi Product nahi hai!</b>\n\nPehle '📦 Add Product' se product add karo.", btn([[["⬅️ Back","backadmin"]]]));
        return;
    }
    $msg = "🗑️ <b>Kis Product ko Delete karna hai?</b>\n\n⚠️ <b>Warning:</b> Product delete karne se uske saare plans aur keys bhi delete ho jayenge!";
    $kb = [];
    foreach($products as $id => $p){
        $plan_count = isset($p['plans']) ? count($p['plans']) : 0;
        $kb[] = [["❌ {$p['name']} (".$plan_count." plans)", "delprod_$id"]];
    }
    $kb[] = [["⬅️ Back","backadmin"]];
    editMsg($chat_id, $message_id, $msg, btn($kb));
}

function sendSelectProductForEdit($chat_id, $message_id){
    $products = json_decode(file_get_contents("products.json"), true);
    if(count($products) == 0){
        editMsg($chat_id, $message_id, "❌ <b>Koi Product nahi hai!</b>", btn([[["⬅️ Back","backadmin"]]]));
        return;
    }
    $msg = "✏️ <b>Kis Product ko Edit karna hai?</b>";
    $kb = [];
    foreach($products as $id => $p){
        $kb[] = [["{$p['name']} (".count($p['plans'])." plans)", "editprod_$id"]];
    }
    $kb[] = [["⬅️ Back","backadmin"]];
    editMsg($chat_id, $message_id, $msg, btn($kb));
}

// ========== USER FUNCTIONS ==========
function sendMainMenu($chat_id, $name, $balance, $message_id = 0){
    $msg = "👑 ———— <b>BMWytx SELLING BOT</b> ———— 👑\n\n🧡 Yo — ꨄ <b>$name</b>, Welcome Back!!\n\n🔥 ———— WHY CHOOSE US ———— 🔥\n\n🔑 Genuine Premium Keys\n⚡ Instant Auto Delivery\n🛡️ Secure UPI Payments\n💎 Unbeatable Prices\n👊 Real 24/7 Support\n——————————————————————\n💰 Let's get you a key!\n\n💲 <b>Your Balance: ₹$balance.00</b>";
    $kb = [
        [["🛒 Products","shop"]],
        [["📦 My Orders","orders"],["👤 Profile","profile"]],
        [["💰 Add Balance","addbal"],["📄 Payment Proof","proof"]],
        [["📖 How to Use","howto"],["💬 Support","support"]]
    ];
    if($message_id > 0) editMsg($chat_id, $message_id, $msg, btn($kb)); 
    else sendMessage($chat_id, $msg, btn($kb));
}

function sendAllProducts($chat_id, $message_id){
    $products = json_decode(file_get_contents("products.json"), true);
    $msg = "🛒 <b>PRODUCT STORE</b>\n\n📦 All available products:";
    $kb = [];
    
    if(count($products) == 0){
        $msg = "🛒 <b>PRODUCT STORE</b>\n\n❌ No products available yet.";
    } else {
        foreach($products as $id => $p){
            $plan_count = isset($p['plans']) ? count($p['plans']) : 0;
            $kb[] = [[$p['name'] . " (" . $plan_count . " plans)", "buy_$id"]];
        }
    }
    
    $kb[] = [["⬅️ Back","back"]];
    editMsg($chat_id, $message_id, $msg, btn($kb));
}

function sendOrders($chat_id, $message_id, $user_id){
    $orders = json_decode(file_get_contents("orders.json"), true);
    $my_orders = [];
    foreach($orders as $id => $o){ 
        if($o['user'] == $user_id) $my_orders[$id] = $o; 
    }
    if(count($my_orders) == 0){
        $msg = "📄 <b>RECIPT</b>\n\nYou haven't made any purchases yet.";
        $kb = [[["« Back to Menu","back"]]];
    }else{
        $msg = "📦 <b>My Orders</b>\n\n";
        $i = 1;
        foreach(array_reverse($my_orders) as $id => $o){
            $msg .= "$i. <b>{$o['product_name']}</b>\n 📅 Plan: {$o['days']} Days\n 💰 Price: ₹{$o['price']}\n 📅 Date: {$o['date']}\n 🆔 Order: <code>$id</code>\n 🔑 Key: <code>{$o['key']}</code>\n Status: ✅ {$o['status']}\n\n";
            $i++;
        }
        $kb = [[["⬅️ Back to Menu","back"]]];
    }
    editMsg($chat_id, $message_id, $msg, btn($kb));
}

function sendProfile($chat_id, $message_id, $user_id){
    global $balances, $users, $orders; 
    $name = $users[$user_id]['name'] ?? "User"; 
    $join = $users[$user_id]['join'] ?? date("d M Y"); 
    $balance = $balances[$user_id] ?? 0; 
    $total_orders = 0; 
    foreach($orders as $o){ 
        if($o['user'] == $user_id && $o['status'] == "Delivered") $total_orders++; 
    } 
    $msg = "—\n👤 <b>YOUR PROFILE</b>\n—\n\n👹 <b>Name:</b> $name\n🆔 <b>User ID:</b> <code>$user_id</code>\n📅 <b>Member Since:</b> $join\n🏷️ <b>Account Type:</b> 👤 Regular\n💰 <b>Balance:</b> ₹$balance.00\n🛒 <b>Total Orders:</b> $total_orders\n—"; 
    $kb = [[["🛒 Products","shop"],["📦 My Orders","orders"]],[["⬅️ Back to Menu","back"]]]; 
    editMsg($chat_id, $message_id, $msg, btn($kb)); 
}

function sendProof($chat_id, $message_id){ 
    $settings = json_decode(file_get_contents("settings.json"), true); 
    $link = $settings['proof_link']; 
    $msg = "📄 <b>Payment Proof Channel</b>\n\nYaha sabhi payment proof milenge\n🔗 <a href='$link'>Click Here</a>"; 
    editMsg($chat_id, $message_id, $msg, btn([[["⬅️ Back to Menu","back"]]])); 
}

function sendHowTo($chat_id, $message_id){ 
    $settings = json_decode(file_get_contents("settings.json"), true); 
    $link = $settings['howto_link']; 
    $msg = "📖 <b>How to Use</b>\n\n1. Add Balance\n2. Products\n3. Key Instant Milega\nVideo Tutorial:\n🔗 <a href='$link'>Watch Now</a>"; 
    editMsg($chat_id, $message_id, $msg, btn([[["⬅️ Back to Menu","back"]]])); 
}

function sendSupport($chat_id, $message_id){ 
    $settings = json_decode(file_get_contents("settings.json"), true); 
    $user = $settings['support_user']; 
    $msg = "💬 <b>Support</b>\n\nKoi dikkat ho to message karo\n🔗 <a href='https://t.me/".ltrim($user,'@')."'>$user</a>\n\n24/7 Available"; 
    editMsg($chat_id, $message_id, $msg, btn([[["⬅️ Back to Menu","back"]]])); 
}

function sendAddBalance($chat_id, $message_id, $user_id){ 
    global $balances; 
    $bal = $balances[$user_id] ?? 0; 
    $msg = "💸 <b>Add Balance</b>\n\nCurrent balance: ₹$bal.00\nPick a quick amount below, or enter a custom amount.\nMin: ₹1.00 • Max: ₹5,000.00\n⚠️ QR 5 Minute me expire ho jayega"; 
    $kb = [
        [["₹50","pay_50"],["₹100","pay_100"],["₹200","pay_200"]],
        [["₹500","pay_500"],["₹1000","pay_1000"],["₹2000","pay_2000"]],
        [["✏️ Custom Amount","custom"]],
        [["🔙 Back to Menu","back"]]
    ]; 
    editMsg($chat_id, $message_id, $msg, btn($kb)); 
}

function sendKeypad($chat_id, $message_id, $user_id, $amount){ 
    $msg = "💰 <b>Enter Amount</b>\n\n₹$amount\nMin: ₹1.00 • Max: ₹5,000.00"; 
    $kb = [
        [["1","key_1"],["2","key_2"],["3","key_3"]],
        [["4","key_4"],["5","key_5"],["6","key_6"]],
        [["7","key_7"],["8","key_8"],["9","key_9"]],
        [["C","key_C"],["0","key_0"],["⌫","key_DEL"]],
        [["✅ Confirm ₹$amount","confirm_$amount"]],
        [["👋 Back","backkey"]]
    ]; 
    editMsg($chat_id, $message_id, $msg, btn($kb)); 
}

function handleKeypad($chat_id, $message_id, $user_id, $key){ 
    $temp = json_decode(file_get_contents("temp.json"), true); 
    $amount = $temp[$user_id]['amount'] ?? "0"; 
    if($key == "C") $amount = "0"; 
    else if($key == "DEL"){ 
        $amount = substr($amount, 0, -1); 
        if($amount == "") $amount = "0"; 
    } else { 
        $amount = $amount == "0" ? $key : $amount.$key; 
    } 
    $temp[$user_id]['amount'] = $amount; 
    file_put_contents("temp.json", json_encode($temp)); 
    sendKeypad($chat_id, $message_id, $user_id, $amount); 
}

// ========== PAYMENT FUNCTIONS ==========

function loadJsonSafe($file, $default = []){
    if(!file_exists($file)) return $default;

    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}

function saveJsonSafe($file, $data){
    return file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    ) !== false;
}

function getFamApiKey(){
    $key = getenv("FAMGATEWAY_API_KEY");

    if(!empty($key)) return trim($key);

    $settings = loadJsonSafe("settings.json", []);
    return trim($settings["api_key"] ?? "");
}


/*
 * CREATE PAYMENT ORDER
 * ₹50 etc. click karte hi QR generate hoga
 */
function createFamPayOrder($chat_id, $message_id, $user_id, $amount){

    $amount = (float)$amount;
    $api_key = getFamApiKey();

    if(empty($api_key)){
        editMsg(
            $chat_id,
            $message_id,
            "❌ FamGateway API key set nahi hai.",
            btn([
                ["⬅️ Back","backkey"]
            ])
        );
        return;
    }

    if($amount < 1 || $amount > 5000){
        editMsg(
            $chat_id,
            $message_id,
            "❌ Amount ₹1 se ₹5000 ke beech me hona chahiye.",
            btn([
                ["⬅️ Back","backkey"]
            ])
        );
        return;
    }


    /*
     * IMPORTANT:
     * Ye tumhare existing API endpoint ke hisaab se hai.
     */
    $url = "https://fampaygateway.site/api/create_order.php?" .
           http_build_query([
               "amount" => $amount,
               "api_key" => $api_key
           ]);


    $context = stream_context_create([
        "http" => [
            "timeout" => 30
        ]
    ]);


    $raw = @file_get_contents($url, false, $context);

    if($raw === false){

        editMsg(
            $chat_id,
            $message_id,
            "❌ Payment gateway se connection nahi ho paya. Dubara try karo.",
            btn([
                ["⬅️ Back","backkey"]
            ])
        );

        return;
    }


    $res = json_decode($raw, true);


    if(
        !is_array($res) ||
        ($res["status"] ?? "") !== "success"
    ){

        $error = $res["message"] ?? "Unknown gateway error";

        editMsg(
            $chat_id,
            $message_id,
            "❌ Order create nahi hua.\n\n<b>Error:</b> " .
            htmlspecialchars($error),
            btn([
                ["🔄 Try Again","pay_".(int)$amount],
                ["⬅️ Back","backkey"]
            ])
        );

        return;
    }


    $data = $res["data"] ?? [];


    $order_id = $data["order_id"] ?? "";

    if(empty($order_id)){

        editMsg(
            $chat_id,
            $message_id,
            "❌ Gateway ne Order ID return nahi ki.",
            btn([
                ["⬅️ Back","backkey"]
            ])
        );

        return;
    }


    $qr_url = $data["qr_url"] ?? "";
    $upi_id = $data["upi_id"] ?? "";


    /*
     * Payment order save
     */
    $orders = loadJsonSafe("orders.json", []);

    $expire_time = time() + (5 * 60);

    $orders[$order_id] = [
        "user" => $user_id,
        "chat_id" => $chat_id,
        "amount" => $amount,
        "status" => "pending",
        "credited" => false,
        "created" => time(),
        "expire" => $expire_time
    ];

    saveJsonSafe("orders.json", $orders);


    $expire_display = date("H:i:s", $expire_time);


    $msg =
        "💸 <b>Payment Request</b>\n\n" .
        "💰 Amount: ₹" . number_format($amount, 2) . "\n" .
        "🆔 Order ID: <code>" . htmlspecialchars($order_id) . "</code>\n";


    if(!empty($upi_id)){
        $msg .= "📱 UPI ID: <code>" .
                htmlspecialchars($upi_id) .
                "</code>\n";
    }


    $msg .=
        "⏰ Expire: " . $expire_display . "\n\n" .
        "QR scan karke payment karo.";


    /*
     * FLAT BUTTON FORMAT
     * Tumhare btn() function ke hisaab se
     */
    $buttons = [
        ["🔄 Check Payment","check_$order_id"],
        ["❌ Cancel Order","cancel_$order_id"],
        ["⬅️ Back","backkey"]
    ];


    /*
     * QR available hai to photo bhejo
     */
    if(!empty($qr_url)){

        sendPhoto(
            $chat_id,
            $qr_url,
            $msg,
            btn($buttons)
        );

    } else {

        sendMessage(
            $chat_id,
            $msg . "\n\n⚠️ QR URL gateway ne return nahi ki.",
            btn($buttons)
        );
    }


    if($message_id > 0){
        deleteMsg($chat_id, $message_id);
    }
}


/*
 * CHECK PAYMENT
 */
function checkPayment($chat_id, $message_id, $order_id){

    $api_key = getFamApiKey();

    if(empty($api_key)) return;


    $orders = loadJsonSafe("orders.json", []);


    if(!isset($orders[$order_id])){

        editMsg(
            $chat_id,
            $message_id,
            "❌ Order nahi mila.",
            btn([
                ["⬅️ Back","backkey"]
            ])
        );

        return;
    }


    $order = $orders[$order_id];


    /*
     * Security: order correct user ka hona chahiye
     */
    if(
        isset($order["chat_id"]) &&
        (string)$order["chat_id"] !== (string)$chat_id
    ){

        editMsg(
            $chat_id,
            $message_id,
            "❌ Ye payment order aapka nahi hai.",
            btn([
                ["⬅️ Back","backkey"]
            ])
        );

        return;
    }


    if(!empty($order["credited"])){

        $balances = loadJsonSafe("balances.json", []);

        $balance = (float)($balances[$order["user"]] ?? 0);

        editMsg(
            $chat_id,
            $message_id,
            "✅ Payment already processed!\n\n" .
            "💼 Balance: ₹" . number_format($balance, 2),
            btn([
                ["⬅️ Menu","back"]
            ])
        );

        return;
    }


    if(($order["status"] ?? "") === "cancelled"){

        editMsg(
            $chat_id,
            $message_id,
            "❌ Ye payment order cancel ho chuka hai.",
            btn([
                ["➕ New Payment","backkey"]
            ])
        );

        return;
    }


    if(time() > ($order["expire"] ?? 0)){

        $orders[$order_id]["status"] = "expired";

        saveJsonSafe("orders.json", $orders);

        editMsg(
            $chat_id,
            $message_id,
            "❌ <b>Order Expired!</b>\n\nNaya payment request banao.",
            btn([
                ["➕ New Payment","backkey"]
            ])
        );

        return;
    }


    /*
     * PAYMENT STATUS API
     */
    $url = "https://fampaygateway.site/api/verify.php?" .
           http_build_query([
               "order_id" => $order_id,
               "api_key" => $api_key
           ]);


    $raw = @file_get_contents($url);


    if($raw === false){

        editMsg(
            $chat_id,
            $message_id,
            "❌ Payment check nahi ho paya. Dobara try karo.",
            btn([
                ["🔄 Retry","check_$order_id"],
                ["⬅️ Back","backkey"]
            ])
        );

        return;
    }


    $res = json_decode($raw, true);


    /*
     * SUCCESS
     */
    if(
        is_array($res) &&
        ($res["status"] ?? "") === "success" &&
        !empty($res["data"])
    ){

        $data = $res["data"];


        /*
         * API se paid confirmation honi chahiye
         */
        if(
            isset($data["utr"]) ||
            (($data["payment_status"] ?? "") === "paid")
        ){

            /*
             * Reload before credit
             */
            $orders = loadJsonSafe("orders.json", []);


            if(
                !isset($orders[$order_id]) ||
                !empty($orders[$order_id]["credited"])
            ){

                editMsg(
                    $chat_id,
                    $message_id,
                    "✅ Payment already processed.",
                    btn([
                        ["⬅️ Menu","back"]
                    ])
                );

                return;
            }


            $user_id = $orders[$order_id]["user"];

            /*
             * Always credit original amount
             */
            $amount = (float)$orders[$order_id]["amount"];


            $balances = loadJsonSafe("balances.json", []);


            if(!isset($balances[$user_id])){
                $balances[$user_id] = 0;
            }


            $balances[$user_id] =
                (float)$balances[$user_id] + $amount;


            /*
             * Mark credited BEFORE final save
             */
            $orders[$order_id]["status"] = "paid";
            $orders[$order_id]["credited"] = true;
            $orders[$order_id]["paid_at"] = time();
            $orders[$order_id]["utr"] = $data["utr"] ?? "";


            saveJsonSafe("balances.json", $balances);
            saveJsonSafe("orders.json", $orders);


            $utr = htmlspecialchars(
                $data["utr"] ?? "N/A"
            );


            $new_balance = (float)$balances[$user_id];


            $msg =
                "✅ <b>Payment Successful!</b>\n\n" .
                "💰 ₹" . number_format($amount, 2) .
                " Balance me add ho gaya\n" .
                "💳 UTR: <code>$utr</code>\n" .
                "💼 New Balance: ₹" .
                number_format($new_balance, 2);


            editMsg(
                $chat_id,
                $message_id,
                $msg,
                btn([
                    ["⬅️ Menu","back"]
                ])
            );

            return;
        }
    }


    /*
     * PAYMENT PENDING
     */
    $remaining =
        max(0, ($order["expire"] ?? time()) - time());

    $min = floor($remaining / 60);
    $sec = $remaining % 60;


    editMsg(
        $chat_id,
        $message_id,
        "⏳ <b>Payment Pending</b>\n\n" .
        "🆔 Order: <code>" .
        htmlspecialchars($order_id) .
        "</code>\n" .
        "💰 Amount: ₹" .
        number_format((float)$order["amount"], 2) .
        "\n" .
        "⏱️ Remaining: {$min}m {$sec}s\n\n" .
        "Payment ke baad Check Payment dabao.",
        btn([
            ["🔄 Check Payment","check_$order_id"],
            ["❌ Cancel Order","cancel_$order_id"],
            ["⬅️ Back","backkey"]
        ])
    );
}


/*
 * CANCEL PAYMENT
 */
function cancelOrder($chat_id, $message_id, $order_id){

    $orders = loadJsonSafe("orders.json", []);


    if(!isset($orders[$order_id])) return;


    /*
     * Security
     */
    if(
        isset($orders[$order_id]["chat_id"]) &&
        (string)$orders[$order_id]["chat_id"] !==
        (string)$chat_id
    ){
        return;
    }


    if(!empty($orders[$order_id]["credited"])){

        editMsg(
            $chat_id,
            $message_id,
            "✅ Payment already processed, cancel nahi ho sakta.",
            btn([
                ["⬅️ Menu","back"]
            ])
        );

        return;
    }


    $orders[$order_id]["status"] = "cancelled";
    $orders[$order_id]["cancelled_at"] = time();


    saveJsonSafe("orders.json", $orders);


    editMsg(
        $chat_id,
        $message_id,
        "❌ <b>Order Cancelled</b>\n\nNaya payment kar sakte ho.",
        btn([
            ["➕ New Payment","backkey"]
        ])
    );
}

// ==================== END OF PAYMENT SYSTEM ====================

// ========== SEND FUNCTIONS FOR BROADCAST ==========
function sendVideo($chat_id, $video, $caption, $reply_markup = null){
    global $website;
    $d = ['chat_id'=>$chat_id, 'video'=>$video, 'caption'=>$caption, 'parse_mode'=>'HTML'];
    if($reply_markup) $d['reply_markup'] = $reply_markup;
    return @file_get_contents($website."/sendVideo?".http_build_query($d));
}

function sendVoice($chat_id, $voice, $caption, $reply_markup = null){
    global $website;
    $d = ['chat_id'=>$chat_id, 'voice'=>$voice, 'caption'=>$caption, 'parse_mode'=>'HTML'];
    if($reply_markup) $d['reply_markup'] = $reply_markup;
    return @file_get_contents($website."/sendVoice?".http_build_query($d));
}

function sendDocument($chat_id, $document, $caption, $reply_markup = null){
    global $website;
    $d = ['chat_id'=>$chat_id, 'document'=>$document, 'caption'=>$caption, 'parse_mode'=>'HTML'];
    if($reply_markup) $d['reply_markup'] = $reply_markup;
    return @file_get_contents($website."/sendDocument?".http_build_query($d));
}

// ==================== HELPER FUNCTIONS ====================
function saveBalance($u, $b){
    $balances = json_decode(file_get_contents("balances.json"), true);
    $balances[$u] = $b;
    file_put_contents("balances.json", json_encode($balances));
}

function saveTemp($u, $k, $v){
    $t = json_decode(file_get_contents("temp.json"), true);
    $t[$u][$k] = $v;
    file_put_contents("temp.json", json_encode($t));
}

function btn($a){
    $k = [];
    foreach($a as $r){
        $x = [];
        if(isset($r[0]) && is_array($r[0])){
            foreach($r as $b) {
                // Check if button has URL
                if (isset($b[2]) && $b[2] === 'url') {
                    $x[] = ["text"=>$b[0], "url"=>$b[1]];
                } else {
                    $x[] = ["text"=>$b[0], "callback_data"=>$b[1]];
                }
            }
        } else {
            // Check if button has URL
            if (isset($r[2]) && $r[2] === 'url') {
                $x[] = ["text"=>$r[0], "url"=>$r[1]];
            } else {
                $x[] = ["text"=>$r[0], "callback_data"=>$r[1]];
            }
        }
        $k[] = $x;
    }
    return json_encode(["inline_keyboard"=>$k]);
}

function answerCallback($i){
    global $website;
    @file_get_contents($website."/answerCallbackQuery?callback_query_id=$i");
}

function sendMessage($c, $t, $r=null){
    global $website;
    $d = ['chat_id'=>$c, 'text'=>$t, 'parse_mode'=>'HTML', 'disable_web_page_preview'=>true];
    if($r) $d['reply_markup'] = $r;
    return @file_get_contents($website."/sendMessage?".http_build_query($d));
}

function editMsg($c, $m, $t, $r=null){
    global $website;
    $d = ['chat_id'=>$c, 'message_id'=>$m, 'text'=>$t, 'parse_mode'=>'HTML', 'disable_web_page_preview'=>true];
    if($r) $d['reply_markup'] = $r;
    @file_get_contents($website."/editMessageText?".http_build_query($d));
}

function sendPhoto($c, $p, $cap, $r=null){
    global $website;
    $d = ['chat_id'=>$c, 'photo'=>$p, 'caption'=>$cap, 'parse_mode'=>'HTML'];
    if($r) $d['reply_markup'] = $r;
    return @file_get_contents($website."/sendPhoto?".http_build_query($d));
}

function deleteMsg($c, $m){
    global $website;
    @file_get_contents($website."/deleteMessage?chat_id=$c&message_id=$m");
}
?>
