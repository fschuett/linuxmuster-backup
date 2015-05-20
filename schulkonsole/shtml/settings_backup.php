<html lang="de">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="Content-Language" content="de">
<title><?php echo _("Schulkonsole") ?> - <?php echo _("Einstellungen") ?> - <?php echo _("Backup") ?></title>
<?php include($CFG->shtmldir . '/css.shtml.inc') ?>
<script type="text/javascript" src="schulkonsole.js"></script>
</head>
<body>
<div id="container">
<div id="header">
<h1><?php echo _("Schulkonsole f&uuml;r Netzwerkberater/innen") ?></h1>
<?php echo _("Version") ?> <?php echo $version; ?>
<?php echo $action; ?>
</div>
</div>

<div id="menu">
   <a href="start"><?php echo _("Mein Konto") ?></a>
   <a href="settings"><?php echo _("Einstellungen") ?></a>
   <a href="user"><?php echo _("Benutzer") ?></a>
   <a href="quotas"><?php echo _("Quota") ?></a>
   <a href="rooms"><?php echo _("R&auml;ume") ?></a>
   <a href="printers"><?php echo _("Drucker") ?></a>
   <a href="hosts"><?php echo _("Hosts") ?></a>
   <a href="linbo"><?php echo _("LINBO") ?></a>
   <a href="logout"><?php echo _("Abmelden") ?></a>
</div>

<div id="submenu">
	<a class="separator" href="settings"><?php echo _("Start") ?></a>
	<a href="settings_program"><?php echo _("Schulkonsole") ?></a>
	<a href="settings_users"><?php echo _("Benutzerverwaltung") ?></a>
	<a href="settings_classs"><?php echo _("Klassen") ?></a>
        <a href="settings_projects"><?php echo _("Projekte") ?></a>
	<a class="end" href="settings_backup.php"><?php echo _("Backup") ?></a>
</div>

<div id="content">

<h2><?php echo _("Einstellungen") ?> :: <?php echo _("Backup") ?></h2>

<form method="post" accept-charset="UTF-8">

<h3><?php echo _("Backup") ?></h3>

<table class="settings">
<colgroup span="2" width="50%">
</colgroup>
<tr class="even">
<td><label for="backupdevice"><?php echo _("Backupger&auml;t (z.B. /dev/sdb1 oder NFS-Share)") ?></label></td>
<td><input type="text" name="backupdevice" id="backupdevice" value="<?php echo $backup_conf[backupdevice] ?>" ></td>
</tr>
<tr class="odd">
<td><label for="mountpoint"><?php echo _("Mountpunkt (Verzeichnis, in das das Backupger&auml;t ins Serverdateisystem eingeh&auml;ngt ist)") ?></label></td>
<td><input type="text" name="mountpoint" id="mountpoint" value="<?php echo $backup_conf[mountpoint] ?>" ></td>
</tr>
<tr class="even">
<td><?php echo _("Restoremethode, hd: von Fest-/Wechselplatte, nfs: von NFS-Share") ?></td>
<td>
    <input type="radio" name="restoremethod" id="restoremethodhd" value="hd" 
        <?php write_checked($backup_conf['restoremethod'], "hd") ?> >
<label for="restoremethodhd"><?php echo _("hd") ?></label><br>
<input type="radio" name="restoremethod" id="restoremethodnfs" value="nfs"
               <?php write_checked($backup_conf['restoremethod'], "nfs") ?> >
<label for="restoremethodnfs"><?php echo _("nfs") ?></label><br>
</td>
</tr>
<tr class="odd">
<td><?php echo _("Firewall-Einstellungen sichern?") ?></td>
<td>
<input type="radio" name="firewall" id="firewalltrue" value="1"
       <?php write_checked($backup_conf['firewall'],"1") ?> >
<label for="firewalltrue"><?php echo _("Ja") ?></label><br>
<input type="radio" name="firewall" id="firewallfalse" value="0"
       <?php write_checked($backup_conf['firewall'],"0") ?> >
<label for="firewallfalse"><?php echo _("Nein") ?></label>
</td>
<tr class="even">
<td><?php echo _("Backup verifizieren?") ?></td>
<td>
<input type="radio" name="verify" id="verifytrue" value="1"
       <?php write_checked($backup_conf['verify'],"1") ?> >
<label for="verifytrue"><?php echo _("Ja") ?></label><br>
<input type="radio" name="verify" id="verifyfalse" value="0"
       <?php write_checked($backup_conf['verify'],"0") ?> >
<label for="verifyfalse"><?php echo _("Nein") ?></label>
</td>
</tr>
<tr class="odd">
<td><label for="isoprefix"><?php echo _("Pr&auml;fix f&uuml;r ISO-Dateien") ?></label></td>
<td><input type="text" name="isoprefix" id="isoprefix" value="<?php echo $backup_conf['isoprefix'] ?>"></td>
</tr>
<tr class="even">
<td><label for="mediasize"><?php echo _("Gr&ouml;&szlig;e der ISO-Dateien in MB") ?></label></td>
<td><input size="5" maxlength="4" type="text" name="mediasize" id="mediasize" value="<?php echo $backup_conf['mediasize'] ?>"></td>
</tr>
<tr class="odd">
<td><label for="includedirs"><?php echo _("Einzuschlie&szlig;ende Verzeichnisse, leeres Feld bedeutet alle") ?></label></td>
<td><input type="text" size="40" name="includedirs" id="includedirs" value="<?php echo $backup_conf['includedirs'] ?>"></td>
</tr>
<tr class="even">
<td><label for="excludedirs"><?php echo _("Vom Backup auszuschlie&szlig;ende Verzeichnisse") ?></label></td>
<td><input type="text" size="40" name="excludedirs" id="excludedirs" value="<?php echo $backup_conf['excludedirs'] ?>"></td>
</tr>
<tr class="odd">
<td><label for="services"><?php echo _("W&auml;hrend des Backups herunterzufahrende  Dienste") ?></label></td>
<td><input type="text" size="40" name="services" id="services" value="<?php echo $backup_conf['services'] ?>"></td>
</tr>
<tr class="even">
<td><label for="compression"><?php echo _("Kompressionsgrad") ?></label></td>
<td><input size="2" maxlength="1" type="text" name="compression" id="compression" value="<?php echo $backup_conf['compression'] ?>"></td>
</tr>
<tr class="odd">
<td><?php echo _("Backupger&auml;t nach Backup aush&auml;ngen?") ?></td>
<td>
<input type="radio" name="unmount" id="unmounttrue" value="1"
       <?php write_checked($backup_conf['unmount'],"1") ?> >
<label for="unmounttrue"><?php echo _("Ja") ?></label><br>
<input type="radio" name="unmount" id="unmountfalse" value="0"
       <?php write_checked($backup_conf[unmount],"0") ?> >
<label for="unmountfalse"><?php echo _("Nein") ?></label>
</td>
</tr>
<tr class="even">
<td><label for="keepfull"><?php echo _("Anzahl der vorgehaltenen Vollbackups") ?></label></td>
<td><input type="text" size="2" maxlength="1" name="keepfull" id="keepfull" value="<?php echo $backup_conf[keepfull] ?>"></td>
</tr>
<tr class="odd">
<td><label for="keepdiff"><?php echo _("Anzahl der vorgehaltenen differentiellen Backups") ?></label></td>
<td><input type="text" size="3" maxlength="2" name="keepdiff" id="keepdiff" value="<?php echo $backup_conf[keepdiff] ?>"></td>
</tr>
<tr class="even">
<td><label for="keepinc"><?php echo _("Anzahl der vorgehaltenen inkrementellen Backups") ?></label></td>
<td><input type="text" size="3" maxlength="2" name="keepinc" id="keepinc" value="<?php echo $backup_conf[keepinc] ?>"></td>
</tr>
<tr class="odd">
<td><label for="cronbackup"><?php echo _("Vollautomatische Backups durchf&uuml;hren?") ?></label></td>
<td>
<input type="radio" name="cronbackup" id="cronbackuptrue" value="1"
       <?php write_checked($backup_conf[cronbackup],"1") ?> >
<label for="cronbackuptrue"><?php echo _("Ja") ?></label><br>
<input type="radio" name="cronbackup" id="cronbackupfalse" value="0"
       <?php write_checked($backup_conf[cronbackup],"0") ?> >
<label for="cronbackupfalse"><?php echo _("Nein") ?></label>
</td>
</tr>
</table>

<p>
<input type="submit" name="accept" value="&Auml;nderungen &uuml;bernehmen">
</p>

</form>


</div>

<div id="info" style="display: block;">
<a class="hideinfo" onclick="toggle_visibility('info');" href="#">^</a>

<?php
echo '<div id="status"' . $sk_session->status_class() . '>';
echo '<div></div>';
if(isset($sk_session->status)) {
    echo '<p>' . $sk_session->status . '</p>';
}
if(isset($_SERVER['REMOTE_ADDR'])) {
    echo '<p class="info">';
    echo _("Sitzungsdauer:") . $session_time . '<br>';
    echo _("verbleibend:") .  '<span id="timer">' . $max_idle_hh_mm_ss . '</span></p>';
    echo '<p class="info">' . _("Benutzer:");
    echo '<strong>' . $firstname . ' ' . $surname . '</strong><br>';
    echo _("Raum:");
    if(isset($remote_room)) {
        echo '<strong>' . $remote_room . '</strong><br>';
    } else {
        echo '<strong><?php echo _("unbekannt") ?></strong><br>';
    }
    echo _("Workstation:");
    echo '<strong>' . $remote_workstation . '</strong><br>';
    echo _("IP:") . '<strong>' . $_SERVER['REMOTE_ADDR'] . '</strong><br>';
    if(isset($class)) {
        echo '<p class="info">' . _("aktive Klasse:");
        echo '<strong>' . $class . '</strong></p>';
    }
}
?>
</div>

<h2><?php echo _("Info") ?></h2>

<p><?php echo _("Bearbeiten Sie hier die globalen Einstellungen f&uuml;r
das Serverbackup.") ?></p>
<p><?php echo _("F&uuml;r eine detaillierte Beschreibung der einzelnen Punkte
konsultieren Sie bitte die Dokumentation.") ?></p>

<div id="authors">
</div>

</body>
</html>
