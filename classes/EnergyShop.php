<?php

class EnergyShop {
    private $mysqli;
    private $user;

    public function __construct($mysqli, $user) {
        $this->mysqli = $mysqli;
        $this->user = $user;
    }

    // Felhasználó energiájának lekérése
    public function getUserEnergy() {
        return $this->user->getUserData('energy');
    }

    // Elérhető csomagok lekérdezése
    public function getPackages() {
        $result = $this->mysqli->query("SELECT * FROM energyshop_packages");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Vásárlás feldolgozása
    public function buyPackage($packageId) {
        $userId = $this->user->getUserData('id');

        // Lekérdezzük a felhasználó energiáját
        $userEnergy = $this->getUserEnergy();

        // Lekérdezzük a vásárlási csomagot
        $stmt = $this->mysqli->prepare("SELECT * FROM energyshop_packages WHERE id = ?");
        $stmt->bind_param("i", $packageId);
        $stmt->execute();
        $package = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Ha a csomag nem létezik
        if (!$package) {
            return ["success" => false, "message" => "Invalid package!"];
        }

        // Ha nincs elég energia
        if ($userEnergy < $package['energy_cost']) {
            return ["success" => false, "message" => "Not enough energy!"];
        }

        // Vásárlás végrehajtása (energia levonás)
        $stmt = $this->mysqli->prepare("UPDATE users SET energy = energy - ? WHERE id = ?");
        $stmt->bind_param("di", $package['energy_cost'], $userId);
        $stmt->execute();
        $stmt->close();

        // Felhasználói egyenleg frissítése
        $this->user->updateBalance($package['zero_amount']);

        return ["success" => true, "message" => "Purchase successful! You received {$package['zero_amount']} ZER."];
    }

    public function isValidPackage($packageId) {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) AS cnt FROM energyshop_packages WHERE id = ?");
        $stmt->bind_param("i", $packageId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['cnt'] > 0;
    }
}
?>
