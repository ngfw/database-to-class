<?php
$demo = false;
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="description" content="">
		<meta name="author" content="">
		<title>Database to Class Generator</title>
		<link href="Assets/css/bootstrap.min.css" rel="stylesheet">
		<link href="Assets/prettify/prettify.css" rel="stylesheet">
		<link href="Assets/css/style.css" rel="stylesheet">
		<link href="//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.1.0/css/font-awesome.min.css" rel="stylesheet">
		<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
	</head>
	<body>
		<div class="container">
		<div class="row"><h1 class="text-center">Database to Class Generator</h1></div>
<?php
include (dirname(__FILE__) . "/Classes/ClassGenerator.php");

$generator = new ClassGenerator();
$tables    = $generator->getTables();
foreach ($tables as $table):
	$tableArray[] = $table['tableName'];
endforeach;
if (isset($_GET['table']) and !empty($_GET['table']) and in_array($_GET['table'], $tableArray)):
	$generator->setTable($_GET['table']);
	?>
	<ul class="nav nav-tabs" role="tablist">
											<li class="active"><a href="#report" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> Report</a></li>
											<li><a href="#generated_code" role="tab" data-toggle="tab"><i class="fa fa-code"></i> Generated Code</a></li>
											<li><a href="#howto" role="tab" data-toggle="tab"><i class="fa fa-question-circle"></i> How to use it?</a></li>
											<li><a href="?" ><i class="fa fa-file-code-o"></i> Generate More Classes</a></li>
										</ul>

										<!-- Tab panes -->
										<div class="tab-content ">
											<div class="tab-pane active" id="report">
	<?php
	if (!$demo) {
		echo $generator->writeClass($generator->buildClass());
	} else {
	?>
	<div class="alert alert-warning" role="alert">Application is running in demo mode, functionality is limited </div>
	<?php
}
?>
		</div>
		<div class="tab-pane" id="generated_code">
		Source of <strong><?php
echo $_GET['table'];?>.php</strong> class file:
		<br />
		<br />
		<pre class="prettyprint ">
<?php
if ($demo) {
	echo substr(htmlspecialchars($generator->buildClass()), 0, 1099) . "
		...
		...
		...
		Code is limited in demo";
} else {
	echo htmlspecialchars($generator->buildClass());
}?>
</pre>
		</div>
		<div class="tab-pane " id="howto">

<?php
echo $generator->buildHowToUse($demo);?>
</div>
		<div class="col-md-10">
		</div>
		</div>
		</div>

<?php
else:
	$DBSetting = include (realpath(dirname(__FILE__) . "/dbconfig.php"));
	?>
									<div class="panel panel-default">
									<div class="panel-heading">
									<h3 class="panel-title">Available tables in <?php
	echo $DBSetting['dbname'];?>Database:</h3>
									</div>
									<div class="panel-body">
									<ul class="list-group">
	<?php
	foreach ($tables as $table):
		echo "<li class='list-group-item'><i class='fa fa-database'></i>";
		if (isset($table['primaryKey']) and !empty($table['primaryKey'])):
			echo "<a href='?table=" . $table['tableName'] . "'> <i class='fa fa-database'></i> " . $table['tableName'] . "</a>";
		else:
			echo "<span class='black'><i class='fa fa-database'></i> " . $table['tableName'] . " - <i class='fa fa-exclamation-triangle red'></i> Primary key not found</span>";
		endif;
		echo "</li>";
	endforeach;
	?>
	</ul>
									</div>
									</div>
	<?php
endif;
?>
</div>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
	<script src="Assets/js/bootstrap.min.js"></script>
	<script src="Assets/prettify/prettify.js"></script>
	<script src="Assets/js/script.js?v2"></script>
	</body>
</html>

