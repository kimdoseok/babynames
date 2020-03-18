<?php
class BabyName {
  public function __construct($linesperpage) {
    $this->result = array("pagenum"=>0,"lastpage"=>0,"left5"=>0,"right5"=>0,"left50"=>0,"right50"=>0,"leftpage"=>0,"rightpage"=>0,"offset"=>0,"limit"=>$linesperpage,"lines"=>array());
    $this->dirname = "./names";
    $this->pdo = new PDO("sqlite:./babyname.db");
    $queries = array();
    array_push($queries,"create table if not exists names (name_id integer primary key, name text)");
    array_push($queries,"create index if not exists names_name ON names(name)");
    array_push($queries,"create table if not exists babies (baby_id integer primary key, name_id integer, year integer, sex char(1), population integer, FOREIGN KEY(name_id) REFERENCES names(name_id))");
    array_push($queries,"create index if not exists babies_year ON babies(year)");
    array_push($queries,"create index if not exists babies_population ON babies(population)");
    for ($i=0;$i<count($queries);$i++) {
      $stmt = $this->pdo->prepare($queries[$i]);
      $stmt->execute();
    }

  }

  function isPopulated() {
    $stmt = $this->pdo->prepare("select baby_id from babies limit 1");
    $stmt->execute();
    if ($row = $stmt->fetch()) {
      return true;
    }
    return false;
  }

  function getLeftNavData($page,$numrows) {
    $this->result["lastpage"]=ceil($numrows/$this->result["limit"])-1;
    //settype($this->result["lastpage"],"int");
    $this->result["pagenum"] = $page;
    if ($page<0 || $this->result["pagenum"]>$this->result["lastpage"]) {
      $this->result["pagenum"] = $this->result["lastpage"];
    }
    $this->result["left5"] = $this->result["pagenum"]-5;
    if ($this->result["left5"]<0) {
      $this->result["left5"] = 0;
    }
    $this->result["left50"] = $this->result["pagenum"]-50;
    if ($this->result["left50"]<0) {
      $this->result["left50"] = 0;
    }
    $this->result["leftpage"] = $this->result["pagenum"]-1;
    if ($this->result["leftpage"]<0) {
      $this->result["leftpage"] = 0;
    }
    $this->result["right5"] = $this->result["pagenum"]+5;
    if ($this->result["right5"]>$this->result["lastpage"]) {
      $this->result["right5"] = $this->result["lastpage"];
    }
    $this->result["right50"] = $this->result["pagenum"]+50;
    if ($this->result["right50"]>$this->result["lastpage"]) {
      $this->result["right50"] = $this->result["lastpage"];
    }
    $this->result["rightpage"] = $this->result["pagenum"]-1;
    if ($this->result["rightpage"]>$this->result["lastpage"]) {
      $this->result["rightpage"] = $this->result["lastpage"];
    }

  }
  
  function getData($pagenum) {
    $numrows = 0;
    $stmt = $this->pdo->prepare("select count(year) from babies ");
    $stmt->execute();
    if ($row = $stmt->fetch()) {
      $numrows = $row[0];
    }
    $this->getLeftNavData($pagenum,$numrows);
    $this->result["offset"] = $this->result["pagenum"]*$this->result["limit"];
    $query = sprintf("select a.year,b.name,a.sex,a.population from babies a join names b ON a.name_id=b.name_id order by a.year,a.population desc limit %d OFFSET %d ",$this->result["limit"],$this->result["offset"]);
    $stmt = $this->pdo->prepare($query);
    $stmt->execute();
    $this->result["lines"] = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      array_push($this->result["lines"],$row);
    }
  }
  
  function loadFileData() {
    ini_set('max_execution_time', '300');
    $farr = scandir($this->dirname);
    $this->pdo->beginTransaction();
    $queries = array();
    array_push($queries,"delete from names");
    array_push($queries,"delete from babies");
    for ($i=0;$i<count($queries);$i++) {
      $stmt = $this->pdo->prepare($queries[$i]);
      $stmt->execute();
    }
    $stmt1 = $this->pdo->prepare("SELECT name_id FROM names WHERE name=? ");
    $stmt2 = $this->pdo->prepare("INSERT INTO names (name) VALUES (?)");
    $stmt3 = $this->pdo->prepare("INSERT INTO babies (name_id,year,sex,population) VALUES (?,?,?,?)");
    $stmt4 = $this->pdo->prepare("SELECT last_insert_rowid()");
    for ($i=0;$i<count($farr);$i++) {
      if (substr($farr[$i],0,3)=="yob") {
        $year = substr($farr[$i],3,4);
        settype($year,"int");
        $f = fopen($this->dirname."/".$farr[$i],"r");
        while (! feof($f)) {
          $line = fgets($f);
          $linearr = explode(",",$line); //name,sex,population
          if (count($linearr)<3) {
            continue;
          }
          settype($linearr[2],"int");
          $stmt1->execute(array($linearr[0]));
          $row = $stmt1->fetch();
          $name_id = $row[0];
          if (!$row) {
            $params =array($linearr[0]) ;
            $stmt2->execute($params);
            $stmt4->execute();
            $row = $stmt4->fetch();
            if ($row) {
              $name_id = $row[0];
            }
          }
          $params = array($name_id,$year,$linearr[1],$linearr[2]);
          $stmt3->execute($params);
        }
        fclose($f);
        //echo $year."\n";
      }
    }
    $this->pdo->commit();
  }
}


$bn = new BabyName(10);
if (!$bn->isPopulated()) {
  $bn->loadFileData();
}

if (array_key_exists("pn",$_GET)) {
  $bn->getData($_GET["pn"]);
  echo json_encode($bn->result);
  exit;
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
  <meta http-equiv="Expires" CONTENT="0">
  <meta http-equiv="Cache-Control" CONTENT="no-cache, no-store, must-revalidate, public, max-age=0">
  <meta http-equiv="Pragma" CONTENT="no-cache">
    
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <script LANGUAGE="JavaScript" src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
  <script LANGUAGE="JavaScript" src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
  
  <script LANGUAGE="JavaScript">
    pagenum = 0;
    lines = [];
  </script>
    
  <script LANGUAGE="JavaScript">
    function populateLines(pnum) {
      pagenum = pnum;
      var urlstr = "?pn="+pnum;
      //window.alert(urlstr);      
      $.ajax({url: urlstr }).done(function(result){
        //window.alert(result);
        var results = jQuery.parseJSON( result );
        $("#maintable tbody").find("tr").remove();
				for (i=0;i<results.lines.length;i++) {
					var aline = '<tr><th class="text-left">'+results.lines[i].year+'</th>';
          aline += '<td style="display:none;">'+i+'</td>';
          aline += '<td class="text-left">'+results.lines[i].name+'</td>';
          aline += '<td class="text-left">'+results.lines[i].sex+'</td>';
          aline += '<td class="text-right">'+results.lines[i].population+'</td>';
					$("#maintable").append(aline);
				}
        pagenum = parseInt(results.pagenum);
        lastpage = parseInt(results.lastpage);
        $("#pagedisp").text((pagenum+1)+"/"+(lastpage+1));
      });
      return pagenum;
    }
    
  </script>
    
  <script LANGUAGE="JavaScript">    
    $(document).ready(function (){
      pagenum=populateLines(pagenum);
      $("#tofirst").on("click",function(e){pagenum=populateLines(0);});
      $("#backward50").on("click",function(e){pagenum=populateLines(parseInt(pagenum)-50);});
      $("#backward5").on("click",function(e){pagenum=populateLines(parseInt(pagenum)-5);});
      $("#backward").on("click",function(e){pagenum=populateLines(parseInt(pagenum)-1);});
      $("#forward").on("click",function(e){pagenum=populateLines(parseInt(pagenum)+1);});
      $("#forward5").on("click",function(e){pagenum=populateLines(parseInt(pagenum)+5);});
      $("#forward50").on("click",function(e){pagenum=populateLines(parseInt(pagenum)+50);});
      $("#tolast").on("click",function(e){pagenum=populateLines(-1);});

    });




  </script>
 </head>
  
  <body>
    <div class="container">
      <div class="row">
      <h1 class="text-center">Baby's name, sex and population in each year</h1>
      </div>
      <div class="row">
        <table class="table table-sm table-striped table-hover" id="maintable">
          <thead class="thead">
            <tr>
              <th width="10%">Year</th>
              <th width="60%">Name</th>
              <th width="10%">Sex</th>
              <th width="20%">Count</th>
            </tr>
          </thead>
          <tbody class="tbody">
          </tbody>
        </table>
      </div>
      <div class="row">
        <div id="nav" class="col-6">
          <button type="button" class="btn btn-link" id="tofirst">|&lt;&lt;</button>
          <button type="button" class="btn btn-link" id="backward50">&lt;50</button>
          <button type="button" class="btn btn-link" id="backward5">&lt;5</button>
          <button type="button" class="btn btn-link" id="backward">&lt;1</button>
          <span id="pagedisp"></span>
          <button type="button" class="btn btn-link" id="forward">1&gt;</button>
          <button type="button" class="btn btn-link" id="forward5">5&gt;</button>
          <button type="button" class="btn btn-link" id="forward50">50&gt;</button>
          <button type="button" class="btn btn-link" id="tolast">&gt;&gt;|</button>
        </div>
        <div id="nav1" class="col-6">
	  &nbsp;
	</div>
      </div>
    </div>
  </body>
</html>
