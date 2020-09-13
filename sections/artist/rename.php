<?php
/****************************************************************
 *--------------[  Rename artist  ]-----------------------------*
 * If there is no artist with the target name, it simply        *
 * renames the artist. However, if there is an artist with the  *
 * target name, things gut funky - the artists must be merged,  *
 * along with their torrents.                                   *
 *                                                              *
 * In the event of a merger, the description of THE TARGET      *
 * ARTIST will be used as the description of the final result.  *
 * The same applies for torrents.                               *
 *                                                              *
 * TODO:                                                        *
 * Tags are not merged along with the torrents.                 *
 * Neither are similar artists.                                 *
 ****************************************************************/

authorize();

if (!check_perms('torrents_edit')) {
    error(403);
}

$ArtistID = (int)$_POST['artistid'];
try {
    $artist = new \Gazelle\Artist($ArtistID);
}
catch (\Exception $e) {
    error(404);
}

$oldName = $artist->name();
$newName = \Gazelle\Artist::sanitize($_POST['name']);

if (empty($newName)) {
    error('No new name given.');
}
if ($oldName == $newName) {
    error('The new name is identical to <a href="artist.php?artistname=' . display_str($oldName) .'">the old name</a>."');
}
if (!($oldAliasId = $artist->getAlias($oldName))) {
    error('Could not find existing alias ID');
}

list($TargetAliasID, $TargetArtistID) = $DB->row("
    SELECT AliasID, ArtistID
    FROM artists_alias
    WHERE name = ?
    ", $newName
);

if (!$TargetAliasID || $TargetAliasID == $oldAliasId) {
    // no merge, just rename
    $artist->rename($LoggedUser['ID'], $oldAliasId, $newName);
    $TargetArtistID = $ArtistID;
} else {
    // Merge stuff
    $DB->prepared_query("UPDATE artists_alias SET
            Redirect = ?,
            ArtistID = ?
        WHERE AliasID = ?
        ", $TargetAliasID, $TargetArtistID, $oldAliasId
    );
    $DB->prepared_query("
        UPDATE artists_alias SET
            Redirect = '0'
        WHERE AliasID = ?
        ", $TargetAliasID
    );
    if ($ArtistID != $TargetArtistID) {
        $DB->prepared_query("
            UPDATE artists_alias SET
                ArtistID = ?
            WHERE ArtistID = ?
            ", $TargetArtistID, $ArtistID
        );
        $DB->prepared_query("
            DELETE FROM artists_group
            WHERE ArtistID = ?
            ", $ArtistID
        );
    } else {
        $DB->prepared_query("
            UPDATE artists_group SET
                Name = ?
            WHERE ArtistID = ?
            ", $NewName, $ArtistID);
    }
    $DB->prepared_query("
        SELECT GroupID
        FROM torrents_artists
        WHERE AliasID = ?
        ", $oldAliasId
    );
    $Groups = $DB->collect('GroupID');
    $DB->prepared_query("
        UPDATE IGNORE torrents_artists SET
            AliasID = ?,
            ArtistID = ?
        WHERE AliasID = ?
        ", $TargetAliasID, $TargetArtistID, $oldAliasId
    );
    $DB->prepared_query("
        DELETE FROM torrents_artists
        WHERE AliasID = ?
        ", $oldAliasId
    );
    if (!empty($Groups)) {
        foreach ($Groups as $GroupID) {
            $Cache->delete_value("groups_artists_$GroupID");
            Torrents::update_hash($GroupID);
        }
    }
    $DB->prepared_query("
        SELECT RequestID
        FROM requests_artists
        WHERE AliasID = ?
        ", $oldAliasId
    );
    $Requests = $DB->collect('RequestID');
    $DB->prepared_query("
        UPDATE IGNORE requests_artists SET
            AliasID = ?,
            ArtistID = ?
        WHERE AliasID = ?
        ", $TargetAliasID, $TargetArtistID, $oldAliasId
    );
    $DB->prepared_query("
        DELETE FROM requests_artists
        WHERE AliasID = ?
        ", $oldAliasId
    );
    if (!empty($Requests)) {
        foreach ($Requests as $RequestID) {
            $Cache->delete_value("request_artists_$RequestID");
            Requests::update_sphinx_requests($RequestID);
        }
    }
    if ($ArtistID != $TargetArtistID) {
        $DB->prepared_query("
            SELECT GroupID
            FROM torrents_artists
            WHERE ArtistID = ?
            ", $ArtistID
        );
        $Groups = $DB->collect('GroupID');
        $DB->prepared_query("
            UPDATE IGNORE torrents_artists SET
                ArtistID = ?
            WHERE ArtistID = ?
            ", $TargetArtistID, $ArtistID
        );
        $DB->prepared_query("
            DELETE FROM torrents_artists
            WHERE ArtistID = ?
            ", $ArtistID
        );
        if (!empty($Groups)) {
            foreach ($Groups as $GroupID) {
                $Cache->delete_value("groups_artists_$GroupID");
                Torrents::update_hash($GroupID);
            }
        }

        $DB->prepared_query("
            SELECT RequestID
            FROM requests_artists
            WHERE ArtistID = ?
            ", $ArtistID
        );
        $Requests = $DB->collect('RequestID');
        $DB->prepared_query("
            UPDATE IGNORE requests_artists SET
                ArtistID = ?
            WHERE ArtistID = ?
            ", $TargetArtistID, $ArtistID
        );
        $DB->prepared_query("
            DELETE FROM requests_artists
            WHERE ArtistID = ?
            ", $ArtistID
        );
        if (!empty($Requests)) {
            foreach ($Requests as $RequestID) {
                $Cache->delete_value("request_artists_$RequestID");
                Requests::update_sphinx_requests($RequestID);
            }
        }
        Comments::merge('artist', $ArtistID, $TargetArtistID);
    }
}

// Clear torrent caches
$DB->prepared_query("
    SELECT GroupID
    FROM torrents_artists
    WHERE ArtistID = ?
    ", $ArtistID
);
while (list($GroupID) = $DB->next_record()) {
    $Cache->delete_value("torrents_details_$GroupID");
}

$artist->flushCache();

$artist = new \Gazelle\Artist($TargetArtistID);
$artist->flushCache();
$Cache->delete_value("artists_requests_$TargetArtistID");
$Cache->delete_value("artists_requests_$ArtistID");

header("Location: artist.php?id=$TargetArtistID");
