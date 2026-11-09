<?php

namespace Database\Seeders;

use App\Models\GameRole;
use App\Models\PartyTemplate;
use App\Models\SavedBuild;
use App\Models\SharedLink;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data for local testing with /dev/login. Idempotent.
 */
class DevSeeder extends Seeder
{
    private const PLAYERS = [
        // name, IGN, roles
        ['Dev Player 1', 'TankyBoi', ['1h Mace', 'Heavy Mace']],
        ['Dev Player 2', 'HolyMoly', ['Hallowfall', 'Fallen Staff']],
        ['Dev Player 3', 'Frosty', ['Great Frost Staff', 'Permafrost Prism']],
        ['Dev Player 4', 'Arrows', ['Longbow', 'Crossbow']],
        ['Dev Player 5', 'FillGuy', ['Fill']],
        ['Dev Player 6', 'Boom', ['Bombsquad']],
        ['Dev Player 7', 'Whisper', ['Locus', 'Occult Staff']],
        ['Dev Player 8', 'Heavy', ['Fill Tank']],
    ];

    public function run(): void
    {
        if (! GameRole::exists()) {
            $this->call(GameRoleSeeder::class);
        }

        $admin = $this->devUser('dev-admin', 'Dev Admin', User::ROLE_ADMIN);
        $caller = $this->devUser('dev-caller', 'Dev Caller', User::ROLE_CREATOR);

        $players = [];
        foreach (self::PLAYERS as $i => [$name, $ign, $roles]) {
            $players[] = [$this->devUser('dev-player-'.($i + 1), $name, User::ROLE_USER), $ign, $roles];
        }

        if (SharedLink::withTrashed()->where('title', 'like', '[DEMO]%')->exists()) {
            return;
        }

        $role = fn (string $name) => GameRole::where('name', $name)->value('id');

        $maceBuild = SavedBuild::firstOrNew(['name' => '1H Mace Engage']);
        $maceBuild->fill([
            'role_category' => 'Tank',
            'weapon_id' => $role('1h Mace'),
            'head_id' => $role('Guardian Helmet'),
            'armor_id' => $role('Judicator Armor'),
            'shoe_id' => $role('Royal Shoes'),
            'cape_id' => $role('Martlock Cape'),
            'food_id' => $role('Pork Omelette'),
            'potion_id' => $role('Resistance Potion'),
            'notes' => 'Engage on caller, swap to Heavy Mace if needed.',
        ]);
        $maceBuild->created_by = $admin->id;
        $maceBuild->save();

        $structure = PartyTemplate::emptyStructure(20);
        $structure[1] = ['role' => '1H Mace Engage', 'note' => 'Main engage', 'type' => 'tank', 'build_id' => $maceBuild->id];
        $structure[2] = ['role' => 'Heavy Mace', 'note' => '', 'type' => 'tank', 'build_id' => null];
        $structure[3] = ['role' => 'Hallowfall', 'note' => 'Main heal', 'type' => 'heal', 'build_id' => null];
        $structure[4] = ['role' => 'Fallen Staff', 'note' => '', 'type' => 'heal', 'build_id' => null];
        $structure[5] = ['role' => 'Locus', 'note' => '', 'type' => 'supp', 'build_id' => null];
        foreach (range(6, 12) as $i) {
            $structure[$i] = ['role' => 'Great Frost Staff', 'note' => '', 'type' => 'dps', 'build_id' => null];
        }
        foreach (range(13, 20) as $i) {
            $structure[$i] = ['role' => 'DPS', 'note' => 'Any DPS', 'type' => 'dps', 'build_id' => null];
        }

        $template = PartyTemplate::firstOrCreate(
            ['name' => 'Demo ZvZ 20'],
            ['size' => 20, 'structure' => $structure, 'created_by' => $admin->id],
        );

        $cta = SharedLink::create([
            'creator_id' => $caller->id,
            'title' => '[DEMO] ZvZ Saturday 20:00 UTC',
            'type' => SharedLink::TYPE_CTA,
            'template_snapshot' => $template->structure,
            'expires_at' => now()->addHours(24),
        ]);

        SharedLink::create([
            'creator_id' => $admin->id,
            'title' => '[DEMO] Small scale content',
            'type' => SharedLink::TYPE_CONTENT,
            'expires_at' => now()->addHours(24),
        ]);

        foreach ($players as [$user, $ign, $roles]) {
            $attributes = ['user_id' => $user->id, 'in_game_name' => $ign];
            foreach (['main', 'second', 'third', 'fourth'] as $n => $prefix) {
                $attributes[$prefix.'_role'] = $roles[$n] ?? null;
                $attributes[$prefix.'_role_id'] = isset($roles[$n]) ? $role($roles[$n]) : null;
            }
            $cta->attendees()->create($attributes);
        }
    }

    private function devUser(string $discordId, string $name, string $role): User
    {
        $user = User::firstOrNew(['discord_id' => $discordId]);
        $user->name = $name;
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
