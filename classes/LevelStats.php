<?php

class LevelStats {
    private $mysqli;
    private $config;
    private $user;

    public function __construct($mysqli, $config, $user) {
        $this->mysqli = $mysqli;
        $this->config = $config;
        $this->user = $user;
    }

    public function getTopUsers() {
        $sql = "SELECT id AS user_id, level, xp FROM users ORDER BY level DESC, xp DESC LIMIT 20";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();

        $topUsers = [];
        while ($row = $result->fetch_assoc()) {
            $topUsers[] = $row;
        }

        $stmt->close();
        return $topUsers;
    }
}

?>
