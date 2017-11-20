<?php session_start();

spl_autoload_register('autoloader');

function autoloader($class){
	include("$class.php");
}

$db = new Db('rfidplayer.sqlite');

//inserting data
if( isset($_POST['ADD_TAG']) ){
	$tag_id = $_POST['tag_id'];
	$tag_uri = $_POST['tag_uri'];
  $tag_desc = $_POST['tag_desc'];
	if( $db->insert($tag_id, $tag_uri, $tag_desc) ){
		$msg = array('1', "Tag added successfully.");
	}else{
		$msg = array('0', "Sorry, tag adding failed.");
	}
	$_SESSION['msg'] = $msg;
}

//getting edit items
if( isset($_REQUEST['edit']) ){
	$id = $_GET['id'];
	$data = $db->getById($id)->fetchArray();
}

//updateing data
if( isset($_POST['UPDATE_TAG']) ){
	$id = $_POST['id'];
  $tag_id = $_POST['tag_id'];
	$tag_uri = $_POST['tag_uri'];
  $tag_desc = $_POST['tag_desc'];
	if( $db->update($id, $tag_id, $tag_uri, $tag_desc) ){
		$msg = array('1', "Tag updated successfully.");
	}else{
		$msg = array('0', "Sorry, tag updating failed.");
	}
	$_SESSION['msg'] = $msg;
	header("Location: /");
	exit;
}

//deleting data
if( isset($_REQUEST['delete']) ){
	$id = $_GET['id'];
  if( $db->delete($id) ){
		$msg = array('1', "Tag deleted successfully.");
	}else{
		$msg = array('0', "Sorry, tag deleting failed.");
	}
	$_SESSION['msg'] = $msg;

	header("Location: /");
	exit;
}

//getting last read tag
$lasttag = $db->getLastReadTag()->fetchArray();


// to show updating form
$isEdit = isset($_REQUEST['edit']) ? true : false;

?>

<!DOCTYPE html>
<html>
<head>
	<title>rfidPlayer Tag Editor</title>
	<style type="text/css">
		.red { color: red; }
		.green { color: green; }
    body { font-family: Sans-serif; font-size: 0.875em;}
	</style>
</head>
<body>
	<div style="margin: 0 auto; width: auto; display: inline-block">
		<div>
			<form style="display:<?php echo $isEdit ? 'none':'block'; ?>" action="" method="post">
				<input type="text" name='tag_id' value="<?php echo isset($_REQUEST['add']) ? $_REQUEST['tag_id'] : ''; ?>" placeholder="Enter Tag UID" required="true">
				<input type="text" name='tag_uri' placeholder="Enter Tag URI" required="">
        <input type="text" name='tag_desc' placeholder="Enter Tag Description" required="">
				<input type="submit" name="ADD_TAG" value="Add">
			</form>
      <a style="display:<?php echo $isEdit ? 'none':'block'; ?>" href="tagstats.php">RFID Player Statistics</a>
      
     <!--  <h3 style="display:<?php echo $isEdit ? 'none':'block'; ?>">Last Tag</h3>
      <form style="display:<?php echo $isEdit ? 'none':'block'; ?>" action="" method="post">
				<input type="text" name='tag_id' value="<?php echo isset($lasttag) ? $lasttag['tag_id'] : ''; ?>">
				<input type="text" name='tag_uri' value="<?php echo isset($lasttag) ? $lasttag['tag_uri'] : ''; ?>" placeholder="Enter Tag URI" required="">
        <input type="text" name='tag_desc' value="<?php echo isset($lasttag) ? $lasttag['tag_desc'] : ''; ?>" placeholder="Enter Tag Description" required="">  
				<input type="submit" name="ADD_TAG" value="Add">
			</form> -->  
      
			<form style="display:<?php echo $isEdit ? 'block':'none'; ?>" action="" method="post">
				<input type="hidden" name="id" value="<?php echo isset($data) ? $data['rowid'] : ''; ?>">
        <input type="text" name="tag_id" value="<?php echo isset($data) ? $data['tag_id'] : ''; ?>">
				<input type="text" name='tag_uri' value="<?php echo isset($data) ? $data['tag_uri'] : ''; ?>" placeholder="Enter Tag URI">
				<input type="text" name='tag_desc' value="<?php echo isset($data) ? $data['tag_desc'] : ''; ?>" placeholder="Enter Tag Description" required="">
				<input type="submit" name="UPDATE_TAG" value="Save">
			</form>

			<?php if( isset($_SESSION['msg']) && !empty($_SESSION['msg']) ){ ?>
			<p class="<?php echo $_SESSION['msg'][0]==0 ? 'red' : 'green';?>"><?php echo $_SESSION['msg'][1];?></p>
			<?php } ?>
		</div>
		<table cellpadding="1" border="1" width="100%">
			<tr>
				<td>Tag ID</td>
				<td>URI</td>
				<td>Description</td>
				<td>Action</td>
			</tr>
			<?php 
			$result = $db->getAll();
			//echo "<pre>"; print_r($result->fetchArray());
			//$allData = 
			while($row = $result->fetchArray()) {?>
				
			<tr>
				<td><?php echo $row['tag_id'];?></td>
				<td><?php echo $row['tag_uri'];?></td>
				<td><?php echo $row['tag_desc'];?></td>
				<td>
					<a href="?edit=true&id=<?php echo $row['rowid']; ?>">Edit</a> | 
					<a href="?delete=true&id=<?php echo $row['rowid']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
				</td>
			</tr>
			<?php } ?>

		</table>
	</div>
<?php $_SESSION['msg'] = array(); ?>
</body>	
</html>