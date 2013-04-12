<?php
if(!defined('APPLICATION')) die();

?>
<h1><?php echo sprintf(T('Members in %s group'), $this->GroupName); ?></h1>
<ul class="MembershipsListEmpty">
<?php
$Alt = FALSE;
foreach ($this->GroupMembers->Result() as $User) {
    $Alt = $Alt ? FALSE : TRUE;
?>
    <li<?php echo $Alt ? ' class="Alt"' : ''; ?>>
    <?php echo UserAnchor($User); ?>
    </li>
<?php } ?>
</ul>
