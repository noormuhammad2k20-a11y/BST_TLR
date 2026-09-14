<?php

// Each palette has purpose-designed light and dark surfaces, not inverted colors.
$palette = static function (string $name, string $description, array $light, array $dark): array {
    $keys = ['bg', 'text', 'muted', 'border', 'hover', 'active', 'accent', 'fill'];
    $tokens = static function (array $values) use ($keys): array {
        $t = array_combine($keys, $values);
        return $t + ['logo-bg' => $t['fill'], 'logo-text' => '#ffffff'];
    };
    return ['name' => $name, 'description' => $description, 'light' => $tokens($light), 'dark' => $tokens($dark)];
};

return ['palettes' => [
    'classic' => $palette('Classic', 'Original indigo',
        ['#ffffff','#172033','#526077','#dbe1ec','#f1f4fa','#e6eaff','#4338ca','#4338ca'],
        ['#151827','#f1f3fb','#b3bcd3','#343b55','#242b42','#303955','#a5b4fc','#4338ca']),
    'slate' => $palette('Slate', 'Signature cool gray',
        ['#f3f6fa','#172338','#4c5d73','#d3dce8','#e6edf5','#dbe5f2','#344e75','#344e75'],
        ['#0f172a','#f1f5fb','#b1c0d5','#334155','#1e293b','#293b54','#b0c7f3','#344e75']),
    'navy' => $palette('Navy', 'Established blue',
        ['#f0f5fc','#182d4b','#48617e','#cbd9ea','#e0eaf8','#d3e2f5','#245595','#245595'],
        ['#1b2a4a','#f0f5ff','#b6c8e3','#3d5476','#293e60','#324c70','#9dd7ff','#245595']),
    'teal' => $palette('Harbor', 'Deep ocean teal',
        ['#eff8f7','#163b3b','#416463','#c8deda','#dfefec','#cfe5e1','#17645e','#17645e'],
        ['#112d30','#eaf7f5','#aacbc8','#345356','#214145','#2b5053','#82ded0','#17645e']),
    'forest' => $palette('Evergreen', 'Quiet botanical green',
        ['#f3f7f1','#253c2b','#50634e','#d2decb','#e5edde','#d9e5d1','#386343','#386343'],
        ['#1b2c22','#f0f6ee','#b7cbb2','#3d5441','#2b4030','#36513c','#b2daa7','#386343']),
    'plum' => $palette('Aubergine', 'Refined muted violet',
        ['#f8f3fa','#392641','#695373','#e1d3e7','#eee4f2','#e5d8eb','#70467f','#70467f'],
        ['#2b2033','#f9f0fc','#d0b8db','#55405f','#3d2e48','#4b3857','#ddb5ed','#70467f']),
    'bronze' => $palette('Bronze', 'Warm architectural stone',
        ['#faf6f0','#3e3022','#6f5c45','#e5d7c5','#f0e6d8','#e8dbc6','#79552b','#79552b'],
        ['#2c241c','#faf3e8','#d1bfa5','#584837','#403529','#504131','#edcb97','#79552b']),
    'petrol' => $palette('Petrol', 'Modern blue graphite',
        ['#f0f6f8','#223843','#506774','#ccdde4','#e0ebf0','#d2e3eb','#365e73','#365e73'],
        ['#182b35','#eef6fb','#b1c8d5','#395362','#283e4b','#324e5e','#9bcee9','#365e73']),
]];
