<?php
include     ('../../public/assets/php/partial/require_common.php');
include     ($PATH.'/public/assets/php/lib/common/sessionCheck.php');

require_once($PATH.'/public/assets/php/convertCsvFileToArray.php');
require_once($PATH."/public/assets/php/lib/administrator/administratorProcess.php");

$extension    = 'nofiles';
$displayMonth = '月を選択する';
$displayEdit  = '月のリストを編集する';
$monthlyId    = '';
$editFlag     = true;
$csvFlag      = true;
$csv          = [];
$errors       = [];
$rows         = [];

try {
    $pdo = connectDb('coop');
} catch (Exception $e) {
}
for($i = -3; $i < 3; $i++)
{
    $date = date('Y-m-01');
    if($i <   0) $dateSerial = strtotime($date."{$i} month");
    if($i === 0) $dateSerial = strtotime($date);
    if($i >   0) $dateSerial = strtotime($date."+{$i} month");
    $rows[] = $dateSerial;
}

// option=selectedの調整
//if(){

//}

if(count($_POST) > 0 && isset($_POST['month']))
{
    try {
        // monthlyにデータを作成して、月別IDを取得する
        $monthlyId    = monthlyIdGeneration($_POST['month']);
        // [取込ボタン]月別IDが既に確定済みであれば処理中止
//        $sql  = "SELECT fixed_flag FROM monthly WHERE date=? LIMIT 1;";
        $sql  = "SELECT (fixed_flag + public_flag) FROM monthly WHERE monthly_id=?;";
        $stmt = $pdo->prepare($sql);
        $res  = $stmt->execute([$monthlyId,]);
//        var_dump($stmt->fetch());
        if(!$res) throw new Exception("monthlyDB接続時にエラーが発生しました。");
        if(intval($stmt->fetchColumn()) > 0) $csvFlag = false;
        $displayMonth = date('Y年n月', strtotime($_POST['month']))."が選択されています";

        // [編集画面へのリンク]確定済みでなければリンクを表示させる
//        $sql  = "SELECT COUNT(*)
//                FROM monthly_goods, monthly
//                WHERE monthly_goods.monthly_id=?
//                AND fixed_flag=1;"
//        ;
        $sql = "SELECT COUNT(*)
            FROM monthly_goods
            NATURAL JOIN monthly
            WHERE monthly_goods.monthly_id=?
            AND fixed_flag =0
            AND public_flag =0;"
        ;
        $stmt = $pdo->prepare($sql);
        $res  = $stmt->execute([$monthlyId,]);
        if(!$res) throw new Exception("montyly_goodsDB接続時にエラーが発生しました。");
        $cnt  = $stmt->fetchColumn();
        if(intval($cnt) === 0 || !$cnt) $editFlag = false;

    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        exit();
    }
}

if(isset($_POST['monthlyId'])) $monthlyId = intval($_POST['monthlyId']);

// ファイルがセットされたかの判定
if(count($_FILES) > 0 && is_uploaded_file($_FILES['csv']['tmp_name']))
{
    try
    {
        // ファイル拡張子がCSVであるか判定
        $extension = mb_substr($_FILES['csv']['name'], -3);
        if(strtolower($extension) !== 'csv')
        {
            echo '<script type="text/javascript">alert("CSV形式以外のファイルがアップロードされたようです。");</script>';
            throw new Exception("ファイル形式が誤っています。");
        }
        // 月別IDがPOSTにされているか判定
        if(!isset($_POST['monthlyId'])) throw new Exception("月が選択されていないようです。");

        // tmpファイルを正式にアップロード
        $filePath = $PATH.'/public/assets/files/upload.csv';
        move_uploaded_file($_FILES['csv']['tmp_name'], $filePath);
        chmod($filePath, 0644);
        // CSVファイルを配列に変換
        $csvArray = convertCsvFileToArray($PATH.'/public/assets/files/upload.csv');
        // ファイル内容のチェック
        $errorMessage=csvFileCheck($csvArray);
        // 結果をDBに格納
        if($errorMessage==null){
            productListCreation($csvArray, $_POST['monthlyId']);
        }
        // アップロードしたファイルを削除
        if(file_exists($filePath)) unlink($filePath);
        // ページ遷移
//        exit();
        header('location: ../productlist/index.php?id='.$monthlyId);
    }catch (Exception $e)
    {
        $errors[] = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>CoopSystem</title>
    <?php include($PATH."/public/assets/php/partial/head.php"); ?>
</head>
<body>

<?php include($PATH."/public/assets/php/partial/header.php"); ?>

<button class="col-btn" col-target="#col-menu"></button>

<div class="flex">
    <div class="col-2 border-right min-height" id="col-menu">
        <?php include($PATH."/public/assets/php/partial/menu_admin.php"); ?>
    </div>
    <div class="col-10 container">
        <h2>生協商品リストを編集する</h2>
        <form method="post">
            <select name="month">
                <?php foreach ($rows as $val){ ?>
                <option value="<?php echo date('Ymd', $val); ?>"
                 <?php if(date('Ym') === date('Ym', $val)) echo 'selected' ?>>
                 <?php echo date('Y年n月分', $val) ?></option>
                <?php } ?>
            </select>
            <button type="submit" name='submit_month' class="btn btn-green"><?php echo $displayMonth ?></button>
        </form>

        <?php if(isset($_POST['submit_month'])){ ?>
        <form method="post" action="" enctype="multipart/form-data">
            <input type="file"    name="csv" id="csv">
            <input type="hidden"  name="monthlyId" value="<?php echo $monthlyId ?>">
            <?php if($csvFlag){ ?>
            <button type="submit" name="submit_csv" class="btn btn-blue" onclick="return checkFile();" >商品リストを取り込む</button>
            <?php }else{ ?>
            <p class="text-red">指定した月は既に確定済みであるため商品の追加ができません。</p>
            <?php } ?>
            <?php if($editFlag){ ?>
            <a href="../productlist?id=<?php echo $monthlyId ?>" class="btn btn-yellow"><?php echo $displayEdit ?></a>
            <?php }else{ ?>
            <?php } ?>
        </form>
        <?php } ?>

        <?php errorMessages($errors) ?>
    </div>
</div>
<script type="text/javascript">
    function checkFile(){
        if($('#csv')[0].files[0]) return true;
        alert('ファイルが選択されていません。');
        return false;
    }
</script>
<?php include($PATH."/public/assets/php/partial/footer.php"); ?>
</body>
</html>