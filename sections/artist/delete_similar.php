<?php
authorize();
$SimilarID = db_string($_GET['similarid']);
$PrimaryArtistID = intval($_GET['artistid']);

if (!is_number($SimilarID) || !$SimilarID) {
    error(404);
}
if (!check_perms('site_delete_tag')) {
    error(403);
}
$DB->query("
    SELECT ArtistID
    FROM artists_similar
    WHERE SimilarID = '$SimilarID'");
$ArtistIDs = $DB->to_array();
$DB->query("
    DELETE FROM artists_similar
    WHERE SimilarID = '$SimilarID'");
$DB->query("
    DELETE FROM artists_similar_scores
    WHERE SimilarID = '$SimilarID'");
$DB->query("
    DELETE FROM artists_similar_votes
    WHERE SimilarID = '$SimilarID'");

foreach ($ArtistIDs as $ArtistID) {
    list($ArtistID) = $ArtistID;
    $artist = new \Gazelle\Artist($ArtistID);
    $artist->flushCache();
    $Cache->delete_value("similar_positions_$ArtistID");
}

$Location = (empty($_SERVER['HTTP_REFERER'])) ? "artist.php?id={$PrimaryArtistID}" : $_SERVER['HTTP_REFERER'];
header("Location: {$Location}");
