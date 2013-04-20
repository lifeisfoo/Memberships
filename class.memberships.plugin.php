<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['Memberships'] = array(
   'Name' => 'Memberships',
   'Description' => 'This plugin allows users to be assigned to groups created by the Groups plugin.',
   'Version' => '0.1',
   'RequiredApplications' => FALSE,
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => array('Groups' => '0.1'),
   'SettingsUrl' => '/dashboard/plugin/memberships',
   'SettingsPermission' => 'Garden.Settings.Manage',
   'HasLocale' => TRUE,
   'RegisterPermissions' => FALSE,
   'Author' => "Johnathon Williams",
   'AuthorEmail' => 'john@oddjar.com',
   'AuthorUrl' => 'http://oddjar.com'
);

class MembershipsPlugin extends Gdn_Plugin {
   
   public function Base_GetAppSettingsMenuItems_Handler($Sender) {    
      $LinkText = T('Memberships');
      $Menu = $Sender->EventArguments['SideMenu'];
      $Menu->AddItem('Users', T('Users'));
      $Menu->AddLink('Users', $LinkText, 'plugin/memberships', 'Garden.Settings.Manage');
   }

   public function PluginController_Memberships_Create($Sender) {
      $Sender->Permission('Garden.Settings.Manage');
      $Sender->Title('Membership Management');
      $Sender->AddSideMenu('plugin/memberships');
      $Sender->Form = new Gdn_Form();
      $this->Dispatch($Sender, $Sender->RequestArgs);
   }

   /**
    * Show left panel with groups list
    */
   public function CategoriesController_AfterRenderAsset_Handler($Sender) {
       if(strcmp(GetValue("AssetName", $Sender->EventArguments), "Panel") == 0){
           echo '<div class="Box BoxGroups">';
           echo '<h4><a href="' . Url('groups') . '">' . T('Groups')  . '</a></h4>';
           echo '<ul class="PanelInfo GroupsPanel">';
           $GroupList = Gdn::SQL()->Select('*')
                                  ->From('Group gr')
                                  ->Get();

           while ($GroupItems = $GroupList->NextRow(DATASET_TYPE_ARRAY)) {
               $Name = $GroupItems['Name'];
               $ID = $GroupItems['GroupID'];
               echo '<li>';
               echo '<a href=' . Url('groups/' . Gdn_Format::Url($Name)) . '>' . $Name . '</a>'; 
               echo '</li>';
           }
           echo "</div>";
       }
   }
   
   public function PluginController_MembershipsList_Create($Sender) {
       $Sender->ClearCssFiles();
       $Sender->AddCssFile('style.css');
       $Sender->MasterView = 'default';

       $GroupNameArg = $Sender->RequestArgs[0];
       if(!$GroupNameArg){
           $Sender->GroupList = $this->GetAllGroups();
           $Sender->Render(dirname(__FILE__) . DS . 'views' . DS . 'groups_list.php');
       }else{
           $Found = FALSE;
           $GroupID;
           $GroupName;
           foreach ( $this->GetAllGroups() as $GName => $GID ){
               if( strcmp($GroupNameArg, Gdn_Format::Url($GName)) == 0 ){
                   $Found = TRUE;
                   $GroupID = $GID;
                   $GroupName = $GName;
                   break;
               }
           }
           if($Found){
               $Sender->UserData = Gdn::SQL()->Select('User.*')->From('User')->OrderBy('User.Name')->Where('Deleted',false)->Get();
               $MemberList = Gdn::SQL()->Select('us.UserID, us.Name, us.Email, us.Photo')
                                       ->Select('ug.GroupID')
                                       ->OrderBy('us.Email', 'asc')
                                       ->Select('gr.Name', '', 'GroupName')
                                       ->From('User us')
                                       ->Where('us.Deleted', 0)
                                       ->Where('gr.GroupID', $GroupID)
                                       ->Join('UserGroup ug', 'us.UserID = ug.UserID', 'left')
                                       ->Join('Group gr', 'ug.GroupID = gr.GroupID', 'left')
                                       ->Get();
               $Sender->GroupName = $GroupName;
               $Sender->GroupMembers = $MemberList;
               if( count($Sender->GroupMembers->Result()) > 0){
                   $Sender->Render(dirname(__FILE__) . DS . 'views' . DS . 'membershipslist.php');
               }else{
                   $Sender->Render(dirname(__FILE__) . DS . 'views' . DS . 'empty_membershipslist.php');
               }
           }else{
               throw NotFoundException(T('Group'));
           }
       }
   }
   
   public function UserInfoModule_OnBasicInfo_Handler($Sender) {
       $Groups = $this->GetUserGroupsNameArray($Sender->User->UserID);
       if( count($Groups) > 0 ) {
           echo '<dt>' . T('Member of these groups') . '</dt>';
           echo '<dd>';
           $this->GroupsArrayShow($Groups);
           echo '</dd>';
       }
   }

   /**
    * Return all groups as a map name=>id
    */
   private function GetAllGroups() {
       $Groups = Gdn::SQL()->Select('gr.GroupID', '', 'id')
                           ->Select('gr.Name', '', 'name')
                           ->From('Group gr')
                           ->Get();
       $GroupsArray = array();
       while ($Group = $Groups->NextRow(DATASET_TYPE_ARRAY)) {
           $GroupsArray[$Group['name']] = $Group['id'];
       }
       return $GroupsArray;
   }
   
   /**
    * Return an array with user groups names. Public so can be used by theme hooks
    */
   static public function GetUserGroupsNameArray($UserID) {
       $Membership = Gdn::SQL()->Select('*')
                               ->From('UserGroup ug')
                               ->Where('ug.UserID', $UserID)
                               ->Join('Group gr', 'ug.GroupID = gr.GroupID', 'left')
                               ->Get();
       $MembershipGroups = array();
       while ($Group = $Membership->NextRow(DATASET_TYPE_ARRAY)) {
           $MembershipGroups[] = $Group['Name'];
       }
       return $MembershipGroups;
   }

   public function ProfileController_EditMyAccountAfter_Handler($Sender) {

       $UserID = $Sender->User->UserID;
       if($UserID){
           $Groups = $this->GetUserGroupsNameArray($UserID);

           if( count($Groups) > 0 ){
               //if user have at least a group
               //show groups and a message to explain howto edit it

               echo '<li class="groups">';
               echo '<label for="groups-list" class="groups-list">' . T('You are member of these groups')  . '</label>';
               echo '<div name="groups-list">';
               $this->GroupsArrayShow($Groups);
               echo T('<small><i>[To change your membership contact the webmaster.]</i></small>') ;
               echo '</div></li>';

           }else{
               // else show select (null by default)
               $this->ProfileGroupSelect($Sender);
           }

       }
   }

   /**
    * Display user group membership (only a string).
    * An array of string must be passed as input
    *
    * @access private
    */
   private function GroupsArrayShow($Groups) {
       $GroupsString = null;
       foreach ($Groups as $Group) {
           if ( !$GroupsString ) {//first group
               $GroupsString = $Group;
           } else {
               $GroupsString .= ', ' . $Group;
           }
       }
       echo $GroupsString . ' ';
   }

   /**
    * Display dropdown group select
    *
    * @access private
    */
   private function ProfileGroupSelect($Sender) {
       $Groups = Gdn::SQL()->Select('gr.GroupID', '', 'value')
                           ->Select('gr.Name', '', 'text')
                           ->From('Group gr')
                           ->Get();
       echo "<li>";
       echo $Sender->Form->Label(T('Group'), 'Plugin.Memberships.GroupID');
       echo $Sender->Form->DropDown('Plugin.Memberships.GroupID', $Groups, array('IncludeNull' => TRUE));
       echo "</li>";
   }

   /**
    * Save selected group (if any) when saving the user.
    */
   public function UserModel_AfterSave_Handler($Sender) {
      $FormPostValues = GetValue('FormPostValues', $Sender->EventArguments);
      $GroupID = GetValue('Plugin.Memberships.GroupID', $FormPostValues);
      $UserID = GetValue('UserID', $FormPostValues);
      if( $UserID && $GroupID ){
          Gdn::SQL()->Insert('UserGroup',array(
              'UserID' => $UserID,
              'GroupID' => $GroupID
          ));
      }
   }
   
   public function Controller_Index($Sender) {
      $Sender->AddCssFile('admin.css');
      $Sender->AddCssFile($this->GetResource('design/memberships.css', FALSE, FALSE));
      $GroupCheck = Gdn::SQL()
		 ->Select('gr.GroupID')
		 ->From('Group gr')
		 ->Get()->NumRows();
      $MemberList = Gdn::SQL()
		 ->Select('us.UserID, us.Name, us.Email')
	  	 ->Select('ug.GroupID')
		 ->OrderBy('us.Email', 'asc')
	     ->Select('gr.Name', '', 'GroupName')
	     ->From('User us')
	     ->Where('us.Deleted', 0)
		 ->Join('UserGroup ug', 'us.UserID = ug.UserID', 'left')
		 ->Join('Group gr', 'ug.GroupID = gr.GroupID', 'left')
         ->Get();
      while ($MemberItems = $MemberList->NextRow(DATASET_TYPE_ARRAY)) {
		 $Sender->MemberList[] = $MemberItems;
      }
	  $Sender->GroupCheck = $GroupCheck;
      unset($MemberList);
      $Sender->Render($this->GetView('memberships.php'));
   }

   public function Controller_Edit($Sender) {   
	
      if ($Sender->Form->AuthenticatedPostBack()) {
         $UserID = $Sender->Form->GetValue('Plugin.Memberships.UserID');
		 $GroupID = $Sender->Form->GetValue('Plugin.Memberships.GroupID');
		
		// check for existing membership
		  $Membership = Gdn::SQL()->Select('ug.GroupID', '', 'OldGroupID')
	         ->From('UserGroup ug')
			 ->Where('ug.UserID', $UserID)
	         ->Get();
	
	      $MembershipCheck = $Membership->FirstRow(DATASET_TYPE_ARRAY);

			if ($MembershipCheck['OldGroupID'] > 0) {
				try {
	            Gdn::SQL()->Update('UserGroup ug')
	            ->Set('ug.GroupID', $GroupID)
	            ->Where('ug.UserID', $UserID)
	            ->Put();
	         } catch(Exception $e) {}
			} else {
				Gdn::SQL()->Insert('UserGroup',array(
		         'UserID' => $UserID,
				 'GroupID' => $GroupID
		        ));
			}
         $Sender->StatusMessage = T("Your changes have been saved.");
         $Sender->RedirectUrl = Url('plugin/memberships');

      } else {
		  // send the group data to the form
		  $Arguments = $Sender->RequestArgs;
	      if (sizeof($Arguments) != 2) return;
	      list($Controller, $UserID) = $Arguments;
	
	      $UserInQuestion = Gdn::SQL()->Select('us.UserID, us.Name')
	         ->From('User us')
			 ->Where('us.UserID', $UserID)
	         ->Get();
	      $OldMembership = Gdn::SQL()->Select('ug.GroupID', '', 'OldGroupID')
	         ->From('UserGroup ug')
			 ->Where('ug.UserID', $UserID)
	         ->Get();
		  $Groups = Gdn::SQL()
		     ->Select('gr.GroupID', '', 'value')
		     ->Select('gr.Name', '', 'text')
	         ->From('Group gr')
	         ->Get();
		  $Sender->Groups = $Groups;
		  $Sender->OldMembership = $OldMembership->FirstRow(DATASET_TYPE_ARRAY);
		  $Sender->UserInQuestion = $UserInQuestion->FirstRow(DATASET_TYPE_ARRAY);
	  }
      $Sender->Render($this->GetView('edit.php'));
   }

   public function Structure() {
      $Structure = Gdn::Structure();
      $Structure
         ->Table('UserGroup')
         ->Column('UserID', 'int(11)')
         ->Column('GroupID', 'int(11)')
         ->Set(FALSE, FALSE);
      Gdn::Router()->SetRoute('groups/(.+)', '/plugin/membershipsList/$1', 'Internal');
   }

   public function Setup() {
      $this->Structure();
      SaveToConfig('Plugins.Memberships.Enabled', TRUE);
   }
   
	public function OnDisable() {
		SaveToConfig('Plugins.Memberships.Enabled', FALSE);
	}

}