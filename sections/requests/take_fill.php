<?php
//******************************************************************************//
//--------------- Fill a request -----------------------------------------------//

$RequestID = $_REQUEST['requestid'];
if (!intval($RequestID)) {
    error(0);
}

authorize();

//VALIDATION
if (!empty($_GET['torrentid']) && intval($_GET['torrentid'])) {
    $TorrentID = $_GET['torrentid'];
} else {
    if (empty($_POST['link'])) {
        error('You forgot to supply a link to the filling torrent');
    } else {
        $Link = $_POST['link'];
        if (!preg_match('/'.TORRENT_REGEX.'/i', $Link, $Matches)) {
            error('Your link does not appear to be valid (use the [PL] button to obtain the correct URL).');
        } else {
            $TorrentID = $Matches[4];
        }
    }
    if (!$TorrentID || !intval($TorrentID)) {
        error(404);
    }
}

//Torrent exists, check it's applicable
$DB->prepared_query("
    SELECT
        t.UserID,
        t.Time,
        tg.ReleaseType,
        t.Encoding,
        t.Format,
        t.Media,
        t.HasLog,
        t.HasCue,
        t.HasLogDB,
        t.LogScore,
        t.LogChecksum,
        tg.CategoryID,
        IF(t.Remastered = '1', t.RemasterCatalogueNumber, tg.CatalogueNumber),
        CASE WHEN t.Time + INTERVAL 1 HOUR > now() THEN 1 ELSE 0 END as GracePeriod
    FROM torrents AS t
    LEFT JOIN torrents_group AS tg ON (t.GroupID = tg.ID)
    WHERE t.ID = ?", $TorrentID);

if (!$DB->has_results()) {
    error(404);
}
list($UploaderID, $UploadTime, $TorrentReleaseType, $Bitrate, $Format, $Media, $HasLog, $HasCue, $HasLogDB, $LogScore, $LogChecksum, $TorrentCategoryID, $TorrentCatalogueNumber, $GracePeriod) = $DB->next_record();

$FillerID = intval($LoggedUser['ID']);
$FillerUsername = $LoggedUser['Username'];

$Err = [];
if (!empty($_POST['user']) && check_perms('site_moderate_requests')) {
    $FillerUsername = trim($_POST['user']);
    $DB->prepared_query('
        SELECT ID
        FROM users_main
        WHERE Username = ?', $FillerUsername);
    if (!$DB->has_results()) {
        $Err[] = 'No such user to fill for!';
    } else {
        list($FillerID) = $DB->next_record();
    }
}

if ($GracePeriod && $UploaderID !== $FillerID && !check_perms('site_moderate_requests')) {
    $Err[] = "There is a one hour grace period for new uploads to allow the uploader ($FillerUsername) to fill the request.";
}

$DB->prepared_query('
    SELECT
        Title,
        UserID,
        TorrentID,
        CategoryID,
        ReleaseType,
        CatalogueNumber,
        BitrateList,
        FormatList,
        MediaList,
        LogCue,
        Checksum
    FROM requests
    WHERE ID = ?', $RequestID);
list($Title, $RequesterID, $OldTorrentID, $RequestCategoryID, $RequestReleaseType, $RequestCatalogueNumber, $BitrateList, $FormatList, $MediaList, $LogCue, $Checksum)
    = $DB->next_record();

if (!empty($OldTorrentID)) {
    $Err[] = 'This request has already been filled.';
}
if ($RequestCategoryID !== '0' && $TorrentCategoryID !== $RequestCategoryID) {
    $Err[] = 'This torrent is of a different category than the request. If the request is actually miscategorized, please contact staff.';
}

$CategoryName = $CategoriesV2[$RequestCategoryID - 1];

if ($Format === 'FLAC' && $LogCue && $Media === 'CD') {
    if (strpos($LogCue, 'Log') !== false) {
        if (!$HasLogDB) {
            $Err[] = 'This request requires a log.';
        } else {
            if (preg_match('/(\d+)%/', $LogCue, $Matches) && $LogScore < $Matches[1]) {
                $Err[] = 'This torrent\'s log score is too low.';
            }

            if ($Checksum && !$LogChecksum) {
                $Err[] = 'The ripping log for this torrent does not have a valid checksum.';
            }
        }
    }

    if (strpos($LogCue, 'Cue') !== false && !$HasCue) {
        $Err[] = 'This request requires a cue file.';
    }
}

if ($BitrateList === 'Other') {
    if (in_array($Bitrate, ['24bit Lossless', 'Lossless', 'V0 (VBR)', 'V1 (VBR)', 'V2 (VBR)', 'APS (VBR)', 'APX (VBR)', '256', '320'])) {
        $Err[] = "$Bitrate is not an allowed bitrate for this request.";
    }
} elseif ($BitrateList && $BitrateList != 'Any' && !Misc::search_joined_string($BitrateList, $Bitrate)) {
    $Err[] = "$Bitrate is not an allowed bitrate for this request.";
}
if ($FormatList && $FormatList != 'Any' && !Misc::search_joined_string($FormatList, $Format)) {
    $Err[] = "$Format is not an allowed format for this request.";
}
if ($MediaList && $MediaList != 'Any' && !Misc::search_joined_string($MediaList, $Media)) {
    $Err[] = "$Media is not a permitted media for this request.";
}

if (count($Err)) {
    error(implode('<br />', $Err));
}

//We're all good! Fill!
$DB->prepared_query('
    UPDATE requests
    SET FillerID = ?,
        TorrentID = ?,
        TimeFilled = now()
    WHERE ID = ?',
    $FillerID, $TorrentID, $RequestID);

$ArtistForm = Requests::get_artists($RequestID);
$ArtistName = Artists::display_artists($ArtistForm, false, true);
$FullName = $ArtistName.$Title;

$DB->prepared_query('
    SELECT UserID
    FROM requests_votes
    WHERE RequestID = ?', $RequestID);
$UserIDs = $DB->to_array();
foreach ($UserIDs as $User) {
    list($VoterID) = $User;
    Misc::send_pm($VoterID, 0, "The request \"$FullName\" has been filled", 'One of your requests&#8202;&mdash;&#8202;[url='.site_url()."requests.php?action=view&amp;id=$RequestID]$FullName".'[/url]&#8202;&mdash;&#8202;has been filled. You can view it here: [url]'.site_url()."torrents.php?torrentid=$TorrentID".'[/url]');
}

$RequestVotes = Requests::get_votes_array($RequestID);
Misc::write_log("Request $RequestID ($FullName) was filled by user $FillerID ($FillerUsername) with the torrent $TorrentID for a ".Format::get_size($RequestVotes['TotalBounty']).' bounty.');

// Give bounty
$DB->prepared_query('
    UPDATE users_leech_stats
    SET Uploaded = Uploaded + ?
    WHERE UserID = ?', $RequestVotes['TotalBounty'], $FillerID);

$Cache->delete_value("user_stats_$FillerID");
$Cache->delete_value("request_$RequestID");
if ($GroupID) {
    $Cache->delete_value("requests_group_$GroupID");
}

$DB->prepared_query('
    SELECT ArtistID
    FROM requests_artists
    WHERE RequestID = ?', $RequestID);
$ArtistIDs = $DB->to_array();
foreach ($ArtistIDs as $ArtistID) {
    $Cache->delete_value("artists_requests_$ArtistID");
}

Requests::update_sphinx_requests($RequestID);
$SphQL = new SphinxqlQuery();
$SphQL->raw_query("UPDATE requests, requests_delta SET torrentid = $TorrentID, fillerid = $FillerID WHERE id = $RequestID", false);

header("Location: requests.php?action=view&id=$RequestID");
