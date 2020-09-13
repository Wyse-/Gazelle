<?php

use Phinx\Migration\AbstractMigration;

class BookmarksDefaultTime extends AbstractMigration {
    public function up() {
        $this->execute("ALTER TABLE bookmarks_collages
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
        $this->execute("ALTER TABLE bookmarks_requests
            MODIFY Time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function down() {
        $this->execute("ALTER TABLE bookmarks_collages
            MODIFY Time datetime NOT NULL
        ");
        $this->execute("ALTER TABLE bookmarks_requests
            MODIFY Time datetime NOT NULL
        ");
    }
}
