<?php

/*file manager, version 20110505*/;
$fileid = isset($_GET["file"]) ? $_GET["file"] : ".";
$file = $fileid;
$opr = isset($_GET["opr"]) ? $_GET["opr"] : "list";
$confirm = isset($_GET["confirm"]) ? $_GET["confirm"] : 0;

if ($opr == "down") {
    header("Content-Type: application/octet-stream");
    header("Accept-Ranges: bytes");
    header("Accept-Length: ".filesize($file)); 
    header("Content-Disposition: attachment; filename=\"".basename($file)."\"");
    readfile($file);
    exit();
}

else if ($opr == "view") 
{
    $mimeType = zFmMimeContentTypeGet($file);
    switch ($mimeType) {
        case "":
            print "unknown file type";
            break;
        case "zFm/xls":
            zFmOutputHtmlHeader();
            zFmViewExcel($file);
            break;
        default:
            header("content-type: ".zFmMimeContentTypeGet($file));
            readfile($file);
            break;
    }
}

else if ($opr == "edit")
{
    zFmOutputHtmlHeader();
	print "file <b>".$file."</b>";

	if (!$confirm) {
   		print " <input type=button value=save onclick=\"fmSave();\"><br>";
        print "<script language=javascript> function fmSave() {";
        print "	ffm = document.getElementById(\"fmForm\");";
        print "	if (!ffm)";
        print "		return;";
        print "	ffm.content.value = document.getElementById('fmCtlEdit').value;";
        print "	ffm.submit();";
        print "} </script>";

        print "<form id=fmForm method=post action=\"?opr=edit&file=$fileid&confirm=1\"><input type=hidden name=content></form>";
    
    	$fd = fopen( $file, "r" );
    	if (!$fd)
    	{
    		print "read error!n";
            exit(1);
    	}
    	else
    	{
            $content = fread($fd, filesize($file));
            print "size: ".strlen($content)." bytes<br>";
    		//print htmlspecialchars($content);
    		$content = str_replace("&", "&amp;", $content);
    		$content = str_replace("<", "&lt;", $content);
    		$content = str_replace(">", "&gt;", $content);
       		fclose($fd);

            print "<textarea id=fmCtlEdit cols=80 rows=36 style=\"font-family: 'courier new';\">";
       		print $content;
            print "</textarea>";
    	}
	}

	if ($confirm)
    {
    	//backup
    	date_default_timezone_set('PRC');
    	rename($file, $file.".".gmdate("YmdHis"));
    
    	$content = $_POST["content"];
    	$content = stripslashes($content);
    
    	$fp = fopen($file, "w");
    	fputs($fp, $content);
    	fclose($fp);
    
    	print " New size: ".strlen($content)." bytes<br>";
    }
}

else if ($opr == "rename")
{
    zFmOutputHtmlHeader();
    $oldname = basename($file);
    $dir = substr($file,0,strlen($file)-strlen($oldname));
    if ($confirm) {
        $newname = isset($_GET["newname"]) ? $_GET["newname"] : "";
        if ($newname != "") {
            print "rename $file -> $dir.$newname";
            rename($file, $dir.$newname);
        }
    }
    else {
        print "rename $oldname -> ";
        print "<input type=text id=t value=\"$oldname\">";
        print "<script languague=javascript> function fmRename() { location.href = location.href + '&confirm=1&newname=' + document.getElementById('t').value; } </script>";
        print "<input type=button value=rename onclick=\"fmRename();\">";
    }

}

else if ($opr == "delete")
{
    zFmOutputHtmlHeader();
    if ($confirm) {
        if (is_dir($file))
            rmdir($file);
        else
            unlink($file);
		print "file deleted. <b>$file</b>";
	}
	else {
		print "delete file <b>".$file."</b>";
        print "<script languague=javascript> function fmDelete() { location.href = location.href + '&confirm=1'; }</script>";
        print "<input type=button value=delete onclick=\"fmDelete();\">";
	}
}

else if ($opr == "upload")
{
    $f = /*$HTTP_POST*/$_FILES['file']; 
    print "moving " . $f["tmp_name"] . " to " . "$file/" . $f["name"] . "<br>";
    move_uploaded_file($f["tmp_name"], "$file/"/*dir*/ . $f["name"]);
    print "ok";
}

else if ($opr == "list")
{
    $dir = $file;
    chdir($dir);
    $dir = getcwd();
    $handle = @opendir($dir) or die("Cannot open " . $dir);
    zFmOutputHtmlHeader();
    print "<b>Files in " . $dir . "</b><br/>";
    print "<a href=?opr=list&file=".str_replace(" ", "%20", $dir)."/..>..</a><br>";
    print "<table>\n";
    while ($filename = readdir($handle)) {
        if ($filename == "." || $filename == "..")
            continue;

        $file = "$dir/$filename";
        $fileid = str_replace(" ", "%20", $file);
        print "<tr>";
        if (is_dir($file)) {
            print "<td><a href=?opr=list&file=$fileid/>$filename/</a></td>";
            print "<td></td><td></td>";
        }
        else {
            print "<td><a href=?opr=down&file=$fileid>$filename</a></td>";
            if (zFileViewable($file))
                print "<td><a href=?opr=view&file=$fileid>view</a></td>";
            else
                print "<td></td>";
            if (zFileEditable($file))
                print "<td><a href=?opr=edit&file=$fileid>edit</a></td>";
            else
                print "<td></td>";
        }
        print "<td><a href=?opr=rename&file=$fileid>rename</a></td>";
        print "<td><a href=?opr=delete&file=$fileid>delete</a></td>";
        print "</tr>\n";
    }
    print "</table>\n";
    closedir($handle);

    print "<br><form action=\"?opr=upload&file=".str_replace(" ", "%20", $dir)."\" method=\"post\" enctype=\"multipart/form-data\">";
    print "<input type=file name=file id=file style=\"border: solid 1px grey;\">";
    print "<input type=submit value=upload />";
    print "</form>";
}

function zFmViewExcel($file) {
    error_reporting(E_ALL ^ E_NOTICE);
    require_once 'pe2/excel_reader2.php';
    $data = new Spreadsheet_Excel_Reader($file);
    echo $data->dump(true,true);
}

function zFileExtGet($file) {
    $extend = pathinfo($file);
    if (!isset($extend["extension"]))
        return "";
    $extname = strtolower($extend["extension"]);
    return $extname;
}

function zFmMimeContentTypeGet($file)
{
    $extname = zFileExtGet($file);
    switch ($extname) {
    /*text*/
    case "html":
    case "htm":
        return "text/html";
    case "txt":
    case "php":
    case "asp":
    case "jsp":
    case "css":
    case "js":
    case "c":
    case "h":
    case "cpp":
    case "ini":
    case "bat":
    case "xml":
        return "text/plain";
    /*excel*/
    case "xls":
        return "zFm/xls";
    /*image*/
    case "jpg":
        return "image/jpeg";
    case "jpeg":
        return "image/jpeg";
    case "gif":
        return "image/gif";
    case "png":
        return "image/png";
    default:
        return "text/plain";
    }
}

function zFileViewable($file) {
    switch (zFmMimeContentTypeGet($file)) {
    case "text/plain":
    case "text/html":
    case "image/jpeg":
    case "image/gif":
    case "image/png":
    case "zFm/xls":
        return true;
    default:
        return false;
    }
}

function zFileEditable($file) {
    switch (zFmMimeContentTypeGet($file)) {
    case "text/plain":
    case "text/html":
        return true;
    default:
        return false;
    }
}

function zFmOutputHtmlHeader() {
    print "<head>";
    print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=gbk\" />";
    print "<meta name=\"viewport\" content=\"width=240; initial-scale1=1.4; minimum-scale1=1.0; maximum-scale1=2\" />";
    print "</head>";
    print"<style type=\"text/css\">";
    print"  table {font-family: Times New Roman;}";
    print"</style>";
    
}

?>
