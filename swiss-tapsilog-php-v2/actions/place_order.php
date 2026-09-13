<?php
require_once __DIR__.'/../includes/auth.php';
require_login(); verify_csrf();

/*
 * Vendors order nothing — the order sheet is customer-only. If a vendor
 * session somehow reaches this endpoint, bounce it to their dashboard.
 */
$actor = current_user();
if ($actor && ($actor['role'] ?? 'customer') === 'vendor') {
    safe_redirect('/vendor.php');
}

try {
  /*
   * Server-side store-hours gate: the UI can be bypassed, this cannot.
   */
  if (!accepts_orders()) {
      throw new RuntimeException('The kitchen is closed right now. Please order during store hours.');
  }
  $cart=json_decode($_POST['cart']??'[]',true,512,JSON_THROW_ON_ERROR);
  if(!is_array($cart)||!$cart) throw new RuntimeException('Your order is empty.');
  $ids=[]; foreach($cart as $line){$id=(int)($line['id']??0);$qty=(int)($line['qty']??0);if($id>0&&$qty>0)$ids[$id]=$qty;}
  if(!$ids) throw new RuntimeException('Your order is empty.');

  $paymentMethod = strtolower(trim($_POST['payment_method'] ?? 'cash')) === 'gcash' ? 'gcash' : 'cash';
  $paymentRef = null;
  if ($paymentMethod === 'gcash') {
      $paymentRef = preg_replace('/\D/', '', (string)($_POST['payment_ref'] ?? ''));
      if (strlen($paymentRef) !== 13) {
          throw new RuntimeException('Please enter the 13-digit GCash reference number from your receipt.');
      }
  }

  $place=$pdo->prepare("SELECT id,name,price,available FROM menu_items WHERE id=? LIMIT 1");
  $items=[];$subtotal=0;
  foreach($ids as $id=>$qty){
    $place->execute([$id]); $m=$place->fetch();
    if(!$m||!(int)$m['available']) throw new RuntimeException('One of the selected items is no longer available.');
    $line=(float)$m['price']*$qty;$subtotal+=$line;
    $items[]=['id'=>(int)$m['id'],'name'=>$m['name'],'price'=>(float)$m['price'],'qty'=>$qty];
  }
  $name=trim($_POST['customerName']??'');$phone=trim($_POST['phone']??'');$address=trim($_POST['address']??'');$notes=trim($_POST['notes']??'');
  if($name===''||$phone===''||$address==='') throw new RuntimeException('Please complete the delivery details.');
  $pdo->beginTransaction();
  $stmt=$pdo->prepare("INSERT INTO orders(user_id,customer_name,phone,address,notes,subtotal,delivery_fee,total,status,payment_method,payment_ref) VALUES(?,?,?,?,?,?,?,?, 'pending',?,?)");
  $stmt->execute([$_SESSION['user_id'],$name,$phone,$address,$notes?:null,$subtotal,DELIVERY_FEE,$subtotal+DELIVERY_FEE,$paymentMethod,$paymentRef]);
  $orderId=(int)$pdo->lastInsertId();
  $stmt=$pdo->prepare("INSERT INTO order_items(order_id,menu_item_id,item_name,unit_price,qty) VALUES(?,?,?,?,?)");
  foreach($items as $i)$stmt->execute([$orderId,$i['id'],$i['name'],$i['price'],$i['qty']]);
  $pdo->commit();

  if ($paymentMethod === 'gcash') {
      /*
       * The ticket is done — start the next visit fresh. The header
       * consumes this flag and wipes the device cart, so nothing
       * carries over to the next order or the next customer.
       */
      setcookie('silog_cart_clear', '1', ['expires' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
      $pageTitle='Payment submitted';
      include __DIR__.'/../includes/header.php';
      ?>
      <main class="min-h-[60vh] px-4 py-14">
        <div class="mx-auto max-w-md border border-[#b2e2d2] bg-[#e6f4ef] p-6 text-center text-[#00593d]">
          <p class="font-display text-2xl font-extrabold uppercase">Payment submitted</p>
          <p class="mt-2 text-sm leading-6">We received GCash reference <b class="font-mono"><?= e($paymentRef) ?></b> for <b><?= peso($subtotal + DELIVERY_FEE) ?></b>. The kitchen will verify it shortly — track it under
          <a class="font-semibold underline" href="../orders.php">My Orders</a>.</p>
          <a href="../orders.php" class="mt-4 inline-block bg-[#2b2118] px-5 py-2.5 font-display text-[11px] font-bold uppercase tracking-[.12em] text-white">Track my order →</a>
        </div>
      </main>
      <?php
      include __DIR__.'/../includes/footer.php';
      exit;
  }

  /*
   * The ticket is done — start the next visit fresh. The header consumes
   * this flag and wipes the device cart, so nothing carries over.
   */
  setcookie('silog_cart_clear', '1', ['expires' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
  $_SESSION['flash']=['message'=>'Order placed. Track it under My Orders.','type'=>'success'];
  header('Location: ../orders.php'); exit;
} catch(Throwable $e) {
  if($pdo->inTransaction())$pdo->rollBack();
  $_SESSION['flash']=['message'=>$e->getMessage(),'type'=>'error']; header('Location: ../order.php'); exit;
}
