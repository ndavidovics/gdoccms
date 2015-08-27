<?php
function google_edit($id) {
	echo '<span class="google_doc_class">';
	$page = str_replace('type="text/css">','type="text/css"> .google_doc_class ',str_replace('}', '} .google_doc_class ', file_get_contents('https://docs.google.com/feeds/download/documents/export/Export?id='.$id.'&exportFormat=html')));
	echo strip_tags($page,'<a><p><body><span><div><ul><ol><li><style><img>');
	echo '</span>';
}
?>