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

        // Vásárlás végrehajtása (energia levonás, balance növelés)
        $stmt = $this->mysqli->prepare("UPDATE users SET energy = energy - ?, balance = balance + ? WHERE id = ?");
        $stmt->bind_param("ddi", $package['energy_cost'], $package['zero_amount'], $userId);
        $stmt->execute();
        $stmt->close();

        return ["success" => true, "message" => "Purchase successful! You received {$package['zero_amount']} ZER."];
    }
}
?>
