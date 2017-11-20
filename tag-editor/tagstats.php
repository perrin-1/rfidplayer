<?php session_start();

spl_autoload_register('autoloader');

function autoloader($class){
	include("$class.php");
}

$db = new Db('rfidplayer.sqlite');


?>

<!DOCTYPE html>
<html>
<head>
	<title>rfidPlayer Tag Statistics</title>
	<style type="text/css">
		.red { color: red; }
		.green { color: green; }
    body { font-family: Sans-serif; font-size: 0.875em;}
	</style>
</head>
<body>
	<div style="margin: 0 auto; width: auto; display: inline-block">
		<div>
			<a href="index.php">Back</a>
      <?php //echo "<pre>"; print_r($_SESSION); ?>
			<?php if( isset($_SESSION['msg']) && !empty($_SESSION['msg']) ){ ?>
			<p class="<?php echo $_SESSION['msg'][0]==0 ? 'red' : 'green';?>"><?php echo $_SESSION['msg'][1];?></p>
			<?php } ?>
		</div>
		<table cellpadding="1" border="1" width="100%">
			<tr>
				<td>Tag ID</td>
				<td>Timestamp</td>
				<td>Description</td>
				<td>Action</td>
			</tr>
			<?php 
			$result = $db->getTagStats();
			//echo "<pre>"; print_r($result->fetchArray());
			//$allData = 
			while($row = $result->fetchArray()) {?>
				
			<tr>
				<td><?php echo $row['tag_id'];?></td>
				<td><?php echo $row['timestamp'];?></td>
				<td><?php echo $row['tag_desc'];?></td>
				<td>
					<a href="index.php?add=true&tag_id=<?php echo $row['tag_id']; ?>">Add Tag</a>  
					
				</td>
			</tr>
			<?php } ?>

		</table>
    <a href="index.php">Back</a>
	</div>
<?php $_SESSION['msg'] = array(); ?>
</body>	
</html>