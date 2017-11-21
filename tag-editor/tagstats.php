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
	<div style="margin: 0 auto; width: 100%; max-width: 800px; display: inline-block overflow-x:auto;">
		<div>
      <h1>Tag Statistics for <?php echo gethostname(); ?> </h1>
			<p><a href="index.php">Back</a></p>
      <?php //echo "<pre>"; print_r($_SESSION); ?>
			<?php if( isset($_SESSION['msg']) && !empty($_SESSION['msg']) ){ ?>
			<p class="<?php echo $_SESSION['msg'][0]==0 ? 'red' : 'green';?>"><?php echo $_SESSION['msg'][1];?></p>
			<?php } ?>
		</div>
		<table cellpadding="1" border="1" width="100%">
			<tr>
				<th>Tag ID</th>
				<th>Timestamp</th>
				<th>Description</th>
				<th>Action</th>
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
    <p><a href="index.php">Back</a></p>
	</div>
<?php $_SESSION['msg'] = array(); ?>
</body>	
</html>