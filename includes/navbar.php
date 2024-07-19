<?php
namespace CAH\CAHSA;

$pages = [
    [
        'url' => '/',
        'label' => 'Home',
    ],
    [
        'url' => '/view.php',
        'label' => 'Requests',
    ],
    [
        'url' => '/new.php',
        'label' => 'New Request',
    ],
    [
        'url' => '/logout.php',
        'label' => 'Logout',
    ],
];
?>
<div class="container-fluid bg-faded mb-3">
    <div class="container">
        <nav class="navbar navbar-toggleable-md navbar-light">
            <a class="navbar-brand" href="/<?= getenv('SUBDIRECTORY'); ?>/">
                <h1 class="h5 text-center text-transform-none text-primary-aw">Undergraduate Course Substitution Request</h1>
            </a>
            <button class="navbar-toggler collapsed" type="button" data-toggle="collapse" data-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav ml-auto">
                    <?php foreach ($pages as $page) : ?>
                    <li class="nav-link<?= ($currentPage == 'index' && $page['url'] == '/') || $currentPage == substr($page['url'], 1, strlen($page['url']) - 5) ? ' active' : '' ?>">
                        <a href="<?= '/' . getenv('SUBDIRECTORY') . $page['url'] ?>" class="text-secondary text-decoration-none"><?= $page['label'] ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </nav>
    </div>
</div>
