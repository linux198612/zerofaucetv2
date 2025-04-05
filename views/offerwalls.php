<?php

// Inicializálás
$user = new User($mysqli, $user['id']);
$offerwalls = new Offerwalls($mysqli, $user, $config);

// 📌 API kulcsok és státuszok betöltése
$offerwallsData = $offerwalls->getOfferwallsData();

// Aktív offerwall ellenőrzés
$activeOfferwalls = array_filter($offerwallsData, fn($data) => $data['status'] === "on");

include("header.php");
?>

<div class="container">
    <?php if (!empty($activeOfferwalls)): ?>
        <!-- Nav tabs -->
        <ul class="nav nav-tabs mt-3" id="offerwallsTab" role="tablist">
            <?php $first = true; ?>
            <?php foreach ($offerwallsData as $name => $data): ?>
                <?php if ($data['status'] == "on"): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $first ? 'active' : '' ?>" id="<?= Core::sanitizeOutput($name) ?>-tab"
                                data-bs-toggle="tab" data-bs-target="#<?= Core::sanitizeOutput($name) ?>" type="button"
                                role="tab" aria-controls="<?= Core::sanitizeOutput($name) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>">
                            <?= ucfirst(Core::sanitizeOutput($name)) ?>
                        </button>
                    </li>
                    <?php $first = false; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>

        <!-- Tab panes -->
        <div class="tab-content mt-3">
            <?php $first = true; ?>
            <?php foreach ($offerwallsData as $name => $data): ?>
                <?php if ($data['status'] == "on"): ?>
                    <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" id="<?= Core::sanitizeOutput($name) ?>"
                         role="tabpanel" aria-labelledby="<?= Core::sanitizeOutput($name) ?>-tab">
                        <iframe style="width:100%;height:1000px;border:0;padding:0;margin:0;" 
                                scrolling="yes" frameborder="0"
                                allow="fullscreen; autoplay"
                                src="<?= Core::sanitizeOutput($data['url']) ?>">
                        </iframe>
                    </div>
                    <?php $first = false; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center mt-3">
            No active offerwalls available at the moment.
        </div>
    <?php endif; ?>
</div>

<?php include("footer.php"); ?>

