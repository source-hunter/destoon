<?php
defined('DT_ADMIN') or exit('Access Denied');
include tpl('header');
?>
<div class="tt">�ļ��б�</div>
<table cellpadding="2" cellspacing="1" class="tb">
<tr>
<th>�ļ�</th>
<th width="150">��С</th>
<th width="150">�޸�ʱ��</th>
</tr>
<?php foreach($lists as $v) { ?>
<tr align="center">
<td align="left" class="f_fd">&nbsp;<?php echo str_replace(DT_ROOT.'/file/patch/'.$fid.'/', '', $v);?></td>
<td class="px11"><?php echo dround(filesize($v)/1024);?> Kb</td>
<td class="px11"><?php echo timetodate(filemtime($v), 6);?></td>
</tr>
<?php } ?>
</table>
<?php include tpl('footer');?>