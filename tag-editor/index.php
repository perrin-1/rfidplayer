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
//$lasttag = $db->getLastReadTag()->fetchArray();


// to show updating form
$isEdit = isset($_REQUEST['edit']) ? true : false;

?>

<!DOCTYPE html>
<html>
<head>
	<title>rfidPlayer Tag Editor on <?php echo gethostname(); ?></title>
	<style type="text/css">
		.red { color: red; }
		.green { color: green; }
    body { font-family: Sans-serif; font-size: 0.9em;}
    table {
      border-collapse: collapse;
      width: 100%;
    }
    h, td {
      text-align: left;
      padding: 8px;
    }
    tr:nth-child(even){background-color: #f2f2f2}
    tr.edit-row  {background-color: #f2dede;}
    th {
      background-color: #4CAF50;
      color: white;
    }
    input[type="text"] {
      width: 25%;
    }
	</style>
</head>
<body>
	<div style="margin: 0 auto; width: 100%; max-width: 1000px; display: inline-block overflow-x:auto;">
		<div>
			<h1>rfidPlayer Tag Editor on <?php echo gethostname(); ?> </h1>
      <p style="display:<?php echo $isEdit ? 'none':'block'; ?>">Quick add Tag:
        <form style="display:<?php echo $isEdit ? 'none':'block'; ?>" action="" method="post">
  				<input type="text" name='tag_id' value="<?php echo isset($_REQUEST['add']) ? $_REQUEST['tag_id'] : ''; ?>" placeholder="Enter Tag UID" required="true">
  				<input type="text" name='tag_uri' placeholder="Enter Tag URI" required="">
          <input type="text" name='tag_desc' placeholder="Enter Tag Description" required="">
  				<input type="submit" name="ADD_TAG" value="Add">
  			</form>
      </p>
      <a style="display:<?php echo $isEdit ? 'none':'block'; ?>" href="tagstats.php">RFID Player Statistics</a>
      
     <!--  <h3 style="display:<?php echo $isEdit ? 'none':'block'; ?>">Last Tag</h3>
      <form style="display:<?php echo $isEdit ? 'none':'block'; ?>" action="" method="post">
				<input type="text" name='tag_id' value="<?php echo isset($lasttag) ? $lasttag['tag_id'] : ''; ?>">
				<input type="text" name='tag_uri' value="<?php echo isset($lasttag) ? $lasttag['tag_uri'] : ''; ?>" placeholder="Enter Tag URI" required="">
        <input type="text" name='tag_desc' value="<?php echo isset($lasttag) ? $lasttag['tag_desc'] : ''; ?>" placeholder="Enter Tag Description" required="">  
				<input type="submit" name="ADD_TAG" value="Add">
			</form> -->  
      
      <p style="display:<?php echo $isEdit ? 'block':'none'; ?>">Edit Tag:
  			<form style="display:<?php echo $isEdit ? 'block':'none'; ?>" action="" method="post">
  				<input type="hidden" name="id" value="<?php echo isset($data) ? $data['rowid'] : ''; ?>">
          <input type="text" name="tag_id" value="<?php echo isset($data) ? $data['tag_id'] : ''; ?>">
  				<input type="text" name='tag_uri' value="<?php echo isset($data) ? $data['tag_uri'] : ''; ?>" placeholder="Enter Tag URI">
  				<input type="text" name='tag_desc' value="<?php echo isset($data) ? $data['tag_desc'] : ''; ?>" placeholder="Enter Tag Description" required="">
  				<input type="submit" name="UPDATE_TAG" value="Save">
  			</form>
      </p>

			<?php if( isset($_SESSION['msg']) && !empty($_SESSION['msg']) ){ ?>
			<p class="<?php echo $_SESSION['msg'][0]==0 ? 'red' : 'green';?>"><?php echo $_SESSION['msg'][1];?></p>
			<?php } ?>
		</div>
		<table cellpadding="1" border="1" width="100%">
			<tr>
				<th>Tag ID</th>
				<th>URI</th>
				<th>Description</th>
				<th>Action</th>
			</tr>
			<?php 
			$result = $db->getAll();
			//echo "<pre>"; print_r($result->fetchArray());
			//$allData = 
			while($row = $result->fetchArray()) {
        $isEditCurrentRow = $isEdit && $id == $row['rowid'];
        ?>
				
  			<tr <?php echo $isEditCurrentRow ? 'class="edit-row"':'';?>>
          
  				<td><?php echo $row['tag_id'];?></td>
  				<td><?php echo $row['tag_uri'];?></td>
  				<td><?php echo $row['tag_desc'];?></td>
  				<td>
  					<div style="display: <?php echo $isEditCurrentRow ? 'none':'';?>">
              <a href="?edit=true&id=<?php echo $row['rowid']; ?>">Edit</a> |
    					<a href="?delete=true&id=<?php echo $row['rowid']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
            </div>
            <div style="display: <?php echo $isEditCurrentRow ? '':'none';?>">
              <a href="/">Cancel</a> 
            </div> 
  				</td>
  			</tr>
			<?php } ?>

		</table>
	</div>
<?php $_SESSION['msg'] = array(); ?>
</body>	
</html>