<?php
$i18n = array(
	'add'=>'Ajouter',
	'add_one'=>'Ajouter un élément',
	'admin'=>'Administrateurs',
	'administration'=>'Administration',
	'cache'=>'Mettre en cache (page statique)',
	'close'=>'Fermer',
	'content'=>'Contenu',
	'date'=>'Date',
	'default_value'=>'Valeur par défaut',
	'del'=>'Supprimer',
	'dir_add'=>'Nouveau dossier',
	'dir_edit'=>'Modifier ce dossier',
	'down'=>'↓',
	'edit'=>'Modifier',
	'error_action'=>'Action: Impossible',
	'error_action_msg'=>'Une erreur inconnue s\'est produite, impossible de faire cette action.',
	'error_dir_add'=>'Ce dossier existe déjà',
	'error_dir_add_msg'=>'Un dossier avec ce nom existe déjà à cet emplacement.',
	'error_file_path'=>'Erreur de fichier',
	'error_file_path_msg'=>'Impossible de manipuler les fichiers. Vérifiez le chemin d\'accès et ses permissions.',
	'error_file_size'=>'Trop gros',
	'error_file_size_msg'=>'Le fichier est trop volumineux pour être chargé. Réduisez sa taille.',
	'error_file_type'=>'Type de fichier interdit',
	'error_file_type_msg'=>'Ce type de fichier n\'est pas autorisé à être chargé.',
	'error_last_admin'=>'Dernier compte administrateur',
	'error_last_admin_msg'=>'Vous ne pouvez tous les supprimer. Créez-en un autre d\'abord.',
	'error_last_data'=>'Le dernier survivant',
	'error_last_data_msg'=>'Vous ne pouvez supprimer le dernier élément. Créez-en un autre d\'abord.',
	'error_login'=>'Erreur de connexion',
	'error_login_msg'=>'Mauvaise combinaison identifiant/mot de passe.',
	'error_name_exist'=>'Cet élément existe déjà',
	'error_name_exist_msg'=>'Supprimez-le ou choisissez un autre nom.',
	'error_name_format'=>'Erreur de nommage',
	'error_name_format_msg'=>'Veuillez utiliser uniquement lettres, nombres, points, tirets et tirets bas.',
	'error_name_reserved'=>'Nom réservé',
	'error_name_reserved_msg'=>'Certains noms sont réservés par le système. Choisissez-en un autre.',
	'error_php'=>'Erreur PHP',
	'error_php_msg'=>'L\'erreur suivante a été rencontrée durant le processus : <br />',
	'error_right'=>'Connexion',
	'error_right_msg'=>'Cette action nécessite une authentification.',
	'error_syntax'=>'Erreur de syntaxe',
	'error_syntax_msg'=>'Il y a un cafouillage de moustaches.',
	'fields_list'=>'Liste des champs',
	'file'=>'Fichiers',
	'fileinfo'=>'Infos fichiers',
	'file_del'=>'Supprimer ce fichier',
	'file_name'=>'Nom du fichier',
	'file_upload'=>'Charger un fichier',
	'filter'=>'Filtrer',
	'hide'=>'Cacher',
	'info_add'=>'Un de plus',
	'info_add_msg'=>'L\'élément a été ajouté.',
	'info_del'=>'Un de moins',
	'info_del_msg'=>'L\'élément a été supprimé.',
	'info_dir_add'=>'Dossier créé',
	'info_dir_add_msg'=>'Le nouveau dossier a été créé.',
	'info_dir_del'=>'Dossier supprimé',
	'info_dir_del_msg'=>'Le dossier a été supprimé, ainsi que son contenu.',
	'info_dir_edit'=>'Un nouveau nom',
	'info_dir_edit_msg'=>'Le dossier a été renommé.',
	'info_edit'=>'Transformation !',
	'info_edit_msg'=>'L\'élément a été modifié.',
	'info_field_add'=>'Champ ajouté',
	'info_field_add_msg'=>'Le champ a été ajouté.',
	'info_field_del'=>'Champ supprimé',
	'info_field_del_msg'=>'Le champ a été supprimé.',
	'info_file_add'=>'Fichier chargé',
	'info_file_add_msg'=>'Le fichier a été chargé, il apparaît dans le navigateur de fichiers.',
	'info_file_del'=>'Fichier supprimé',
	'info_file_del_msg'=>'Le fichier a été supprimé.',
	'info_file_edit'=>'Fichier modifié',
	'info_file_edit_msg'=>'Le fichier a été correctement modifié.',
	'info_firstlogin'=>'Création de compte',
	'info_firstlogin_msg'=>'Merci de définir le nom et le mot de passe du nouveau compte administrateur.',
	'info_preview'=>'Ceci est un aperçu',
	'info_preview_msg'=>'Rien ne sera enregistré tant que vous n\'aurez pas cliqué sur "Modifier".',
	'info_undo'=>'Marche arrière toute',
	'info_undo_msg'=>'L\'action a été correctement annulée.',
	'info_update'=>'Nouvelle mise à jour',
	'info_update_msg'=>'Une nouvelle version du système est disponible.',
	'info_updated'=>'Mis à jour',
	'info_updated_msg'=>'Le coeur du système a été mis à jour avec succès.',
	'login'=>'Connexion',
	'login_forgot'=>'Mot de passe oublié?',
	'logout'=>'Déconnexion',
	'name'=>'Nom',
	'new_field'=>'Nouveau champ',
	'new_item' =>'Nouvel élément',
	'no'=>'Non',
	'not_saved'=>'Non enregistré',
	'ok'=>'Ok, merci',
	'page'=>'Pages',
	'parent'=>'Parent',
	'parent_dir'=>'Dossier parent',
	'password'=>'Mot de passe',
	'placeholder_date'=>'Jour/Mois/Année',
	'placeholder_email'=>'email@domaine.fr',
	'placeholder_password'=>'Remplir pour changer de mot de passe',
	'preview'=>'Aperçu',
	'rank'=>'Rang',
	'rank'=>'Rangs',
	'right'=>'Droits',
	'setting'=>'Paramètres',
	'style'=>'Styles',
	'template'=>'Templates',
	'time'=>'Heure',
	'timestamp'=>'Date/heure',
	'title'=>'Titre',
	'toc'=>'Table des matières',
	'toc_empty'=>'Il n\'y a pas de titres structurés.',
	'undo'=>'Annuler',
	'up'=>'↑',
	'update_to'=>'Mettre à jour vers la version',
	'user'=>'Utilisateurs',
	'value'=>'Valeur',
	'visitor'=>'Visiteur',
	'yes'=>'Oui'
);


/*
 * Return a sentence according to a key.
 *
 * @param string $key The key.
 * @return string The sentence.
 */
function word($key)
{
	if(isset($i18n[$key]))
		return $i18n[$key];
	return ucfirst(str_replace('_', ' ', $key));
}



/* english

'add_one'=>'Add a new item',
'cache'=>'Use cache (static page)',
'del'=>'Delete',
'dir_add'=>'New folder',
'dir_edit'=>'Edit this folder',
'down'=>'↓',
'error_dir_add'=>'Already here',
'error_dir_add_msg'=>'This folder already exists here. Please choose another name, or delete it first.',
'error_file_path'=>'File manipulation error',
'error_file_path_msg'=>'Impossible to manipulate files in the given path. Check the files path in setting and check directory permissions (775 needed).',
'error_file_size'=>'Too big',
'error_file_size_msg'=>'The file is too big to be uploaded. Change site setting or turn around.',
'error_file_type'=>'Unauthorized file type',
'error_file_type_msg'=>'This type of file is not allowed to be uploaded.',
'error_last_admin'=>'This is the last admin account',
'error_last_admin_msg'=>'You can\'t delete all the administrators account to not lose control of the website. Create another admin account first.',
'error_last_data'=>'The last one',
'error_last_data_msg'=>'You cannot delete this item because it\' the last of it\'s kind. Please make another first.',
'error_login'=>'Login failed',
'error_login_msg'=>'Invalid name/password combinaison.',
'error_name_exist'=>'This item already exists',
'error_name_exist_msg'=>'An item with this name already exists, delete it or choose another name.',
'error_name_format'=>'Name error',
'error_name_format_msg'=>'Please enter a name using only letters, numbers, points, underscores and dashs.',
'error_name_reserved'=>'This name is reserved',
'error_name_reserved_msg'=>'Some names are reserved for the system. Please choose another one.',
'error_php'=>'PHP error',
'error_php_msg'=>'The following error was encountered during the process : <br />',
'error_right'=>'Login',
'error_right_msg'=>'This require more right. Please place the severed hand on the scanner.',
'fields_list'=>'Fields list',
'file'=>'Files',
'file_del'=>'Delete file',
'file_upload'=>'Upload a file',
'info_add'=>'There is a new one',
'info_add_msg'=>'The value have been added.',
'info_del'=>'One less',
'info_del'=>'One less',
'info_del_msg'=>'The value have been removed.',
'info_dir_add'=>'A new place to be',
'info_dir_add_msg'=>'The folder have been successfully created.',
'info_dir_del'=>'Kaboom',
'info_dir_del_msg'=>'The folder and its content have been successfully deleted.',
'info_dir_edit'=>'A new name',
'info_dir_edit_msg'=>'The folder was renamed successfully.',
'info_edit'=>'Transformation !',
'info_edit_msg'=>'The value have been changed.',
'info_field_add'=>'Field added',
'info_field_add_msg'=>'The new field have been added.',
'info_field_del'=>'Field deleted',
'info_field_del_msg'=>'The field have been deleted.',
'info_file_add'=>'File uploaded',
'info_file_add_msg'=>'The file was uploaded successfully. You should see it in the browser.',
'info_file_del'=>'File deleted',
'info_file_del_msg'=>'The file was successfully deleted.',
'info_file_edit'=>'File edited',
'info_file_edit_msg'=>'The file was successfully edited.',
'info_preview'=>'This is a preview',
'info_preview_msg'=>'No changes will be saved until you use the "Edit" button.',
'info_undo'=>'Action canceled',
'info_undo_msg'=>'The action was successfully canceled.',
'info_update'=>'New update',
'info_update_msg'=>'There is a new update availlable.',
'info_updated'=>'Updated',
'info_updated_msg'=>'The core of thiis was successfully updated.',
'info_firstlogin'=>'Account creation',
'info_firstlogin_msg'=>'Please define the new administrator account name and password.',
'login_forgot'=>'Forgot your password?',
'ok'=>'OK, thanks',
'parent_dir'=>'Parent folder',
'placeholder_date'=>'Day/Month/Year',
'placeholder_email'=>'email@website.com',
'placeholder_password'=>'Fill to change password',
'toc'=>'Table of content',
'toc_empty'=>'There is no structured headers.',
'up'=>'↑',
'update_to'=>'Update core to'
*/