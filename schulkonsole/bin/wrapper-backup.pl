#! /usr/bin/perl

=head1 NAME

wrapper-files.pl - wrapper for writing files

=head1 SYNOPSIS

 my $id = $userdata{id};
 my $password = 'secret';
 my $app_id = Schulkonsole::Config::WRITEFILEAPP;

 open SCRIPT, '|-', $Schulkonsole::Config::_wrapper_files;
 print SCRIPT <<INPUT;
 $id
 $password
 $app_id
 1
 line1
 line2

 INPUT

=head1 DESCRIPTION

=cut

use strict;
use lib '/usr/share/schulkonsole';
use open ':utf8';
use open ':std';
use Schulkonsole::Config;
use Schulkonsole::DB;
use Schulkonsole::Encode;
use Schulkonsole::Error::Files;



my $id = <>;
$id = int($id);
my $password = <>;
chomp $password;

my $userdata = Schulkonsole::DB::verify_password_by_id($id, $password);
exit (  Schulkonsole::Error::Files::WRAPPER_UNAUTHENTICATED_ID
      - Schulkonsole::Error::Files::WRAPPER_ERROR_BASE)
	unless $userdata;

my $app_id = <>;
($app_id) = $app_id =~ /^(\d+)$/;
exit (  Schulkonsole::Error::Files::WRAPPER_APP_ID_DOES_NOT_EXIST
      - Schulkonsole::Error::Files::WRAPPER_ERROR_BASE)
	unless defined $app_id;

my $app_name = $Schulkonsole::Config::_id_root_app_names{$app_id};
exit (  Schulkonsole::Error::Files::WRAPPER_APP_ID_DOES_NOT_EXIST
      - Schulkonsole::Error::Files::WRAPPER_ERROR_BASE)
	unless defined $app_name;



my $permissions = Schulkonsole::Config::permissions_apps();
my $groups = Schulkonsole::DB::user_groups(
	$$userdata{uidnumber}, $$userdata{gidnumber}, $$userdata{gid});

my $is_permission_found = 0;
foreach my $group (('ALL', keys %$groups)) {
	if ($$permissions{$group}{$app_name}) {
		$is_permission_found = 1;
		last;
	}
}
exit (  Schulkonsole::Error::Files::WRAPPER_UNAUTHORIZED_ID
      - Schulkonsole::Error::Files::WRAPPER_ERROR_BASE)
	unless $is_permission_found;


my $opts;
SWITCH: {

=head3 write_file

numeric constant: C<Schulkonsole::Config::WRITEFILEAPP>

=head4 Parameters from standard input

=over

=item file

0 = classrooms, 1 = printers, 2 = workstations, 3 = room_defaults,
4 = backup.conf, 5 = preferences.conf, 6 = group_defaults

=back

=cut

$app_id == Schulkonsole::Config::WRITEFILEAPP and do {
	my $file = <>;
	($file) = $file =~ /^(\d+)$/;
	exit (  Schulkonsole::Error::Files::WRAPPER_INVALID_FILENUMBER
	      - Schulkonsole::Error::Files::WRAPPER_ERROR_BASE)
		unless defined $file;

	my $filename;
	my $perm;
	SWITCHWRITEFILE: {
	$file == 0 and do {
		$filename = Schulkonsole::Encode::to_fs(
		            	$Schulkonsole::Config::_classrooms_file);
		last SWITCHWRITEFILE;
	};
	$file == 1 and do {
		$filename = Schulkonsole::Encode::to_fs(
		            	$Schulkonsole::Config::_printers_file);
		last SWITCHWRITEFILE;
	};
	$file == 2 and do {
		$filename = Schulkonsole::Encode::to_fs(
		            	$Schulkonsole::Config::_workstations_file);
		last SWITCHWRITEFILE;
	};
	$file == 3 and do {
		$filename = Schulkonsole::Encode::to_fs(
		            	$Schulkonsole::Config::_room_defaults_file);
		last SWITCHWRITEFILE;
	};
	$file == 4 and do {
		$filename = Schulkonsole::Encode::to_fs(
		            	$Schulkonsole::Config::_backup_conf_file);
		$perm = 0755 unless -e $filename;
		last SWITCHWRITEFILE;
	};
	$file == 5 and do {
		$filename = Schulkonsole::Encode::to_fs(
		            	$Schulkonsole::Config::_preferences_conf_file);
		$perm = 0644 unless -e $filename;
		last SWITCHWRITEFILE;
	};
        $file == 6 and do {
                $filename = Schulkonsole::Encode::to_fs(
                                $Schulkonsole::Config::_group_defaults_file);
                $perm = 0644 unless -e $filename;
                last SWITCHWRITEFILE;
        };
	}

	$< = $>;
	$) = 0;
	$( = $);
	umask(022);

	open FILE, '>', $filename
		or exit(  Schulkonsole::Error::Files::WRAPPER_ERROR_BASE
		        - Schulkonsole::Error::Files::WRAPPER_CANNOT_OPEN_FILE);
	flock FILE, 2;
	seek FILE, 0, 0;

	while (<>) {
		print FILE;
	}

	if (defined $perm) {
		chmod $perm, $filename;
	}

	close FILE;

	exit 0;
};

=head3 import_workstations

numeric constant: C<Schulkonsole::Config::IMPORTWORKSTATIONSAPP>

=cut

$app_id == Schulkonsole::Config::IMPORTWORKSTATIONSAPP and do {
	my $sid = <>;
	($sid) = $sid =~ /^(.+)$/;
	exit (  Schulkonsole::Error::Files::WRAPPER_INVALID_SESSION_ID
	      - Schulkonsole::Error::Files::WRAPPER_ERROR_BASE)
		unless defined $sid;

	my $pid = fork;
	exit (  Schulkonsole::Error::Files::WRAPPER_CANNOT_FORK
	      - Schulkonsole::Error::Files::WRAPPER_ERROR_BASE)
		unless defined $pid;

	if (not $pid) {
		close STDIN;
		close STDOUT;
		close STDERR;
		open STDOUT, ">>/dev/null" or die;
		open STDERR, ">>&STDOUT" or die;


		umask(027);
		my $lockfile = Schulkonsole::Config::lockfile('import_workstations');
		open LOCK, '>>', Schulkonsole::Encode::to_fs($lockfile)
			or exit(  Schulkonsole::Error::Files::WRAPPER_ERROR_BASE
			        - Schulkonsole::Error::Files::WRAPPER_CANNOT_OPEN_FILE);
		flock LOCK, 2;
		seek LOCK, 0, 0;
		truncate LOCK, 0;
		print LOCK "$$\n";

		$< = $>;
		$) = 0;
		$( = $);
		umask(022);
		open APP, '|-',
		     Schulkonsole::Encode::to_fs(
		     	$Schulkonsole::Config::_cmd_import_workstations)
			or last SWITCH;
		my $line;
		while (<APP>) {
			$line = $_;
		}
		close APP;
		if ($?) {
			my $error_code = $?;
			use CGI::Session;

			my $session_lockfile
				= Schulkonsole::Config::lockfile("cgisession_$sid");
			open SESSIONLOCK, '>>', $session_lockfile
				or exit (  Schulkonsole::Error::Files::WRAPPER_CANNOT_OPEN_FILE
				         - Schulkonsole::Error::Files::WRAPPER_ERROR_BASE);
			flock SESSIONLOCK, 2;

			my $session = new CGI::Session("driver:File", $sid,
			              	{
			                	Directory => Schulkonsole::Encode::to_fs(
			              			$Schulkonsole::Config::_runtimedir)
			              	});
			if ($session->param('username')) {
				chomp $line;
				$session->param('statusbg', $line);
				$session->param('statusbgiserror', $error_code);
				$session->close;
			} else {
				$session->delete();
			}
		}
		exit $?;
	} else {
		exit 0;
	}
};

=head3 import_printers

numeric constant: C<Schulkonsole::Config::IMPORTPRINTERSAPP>

=cut

$app_id == Schulkonsole::Config::IMPORTPRINTERSAPP and do {
	$< = $>;
	$) = 0;
	$( = $);
	umask(022);
	exec Schulkonsole::Encode::to_cli(
	     	$Schulkonsole::Config::_cmd_import_printers)
		or last SWITCH;
};

}



exit -2;	# program error

