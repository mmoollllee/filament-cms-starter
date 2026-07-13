<?php

namespace Database\Factories;

use App\Models\Fragment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Fragment>
 */
class FragmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2, false);

        return [
            'tenant_id' => Tenant::factory(),
            'title' => Str::of($slug)->replace('-', ' ')->title()->value(),
            'slug' => $slug,
            'blocks' => [[
                'type' => 'text',
                'data' => [
                    'active' => true,
                    'title' => null,
                    'layout_preset_ids' => null,
                    'anchor_id' => null,
                    'heading' => 'h2',
                    'eyebrow' => null,
                    'content' => '<p>'.fake()->sentence().'</p>',
                ],
            ]],
        ];
    }
}
