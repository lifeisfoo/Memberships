<?php
if(!defined('APPLICATION')) die();

?>
<div class="groups-list">
<h1><?php T('Select a group'); ?></h1>
<ul>
<?php
foreach ( $this->GroupList as $GName => $GID ){
    $MemberCount = Gdn::SQL()->Select('us.UserID')
                            ->Select('ug.GroupID')
                            ->From('User us')
                            ->Where('us.Deleted', 0)
                            ->Where('gr.GroupID', $GID)
                            ->Join('UserGroup ug', 'us.UserID = ug.UserID', 'left')
                            ->Join('Group gr', 'ug.GroupID = gr.GroupID', 'left')
                            ->GetCount();
    echo '<li class="group">';
    echo '<a href=' . Url('groups/' . Gdn_Format::Url($GName)) . '>' . $GName;
    if($MemberCount > 0){
        echo ' <span>' . $MemberCount . '</span>';
    }
    echo '</a>';
    echo '</li>';
}
?>
</ul>
</div>