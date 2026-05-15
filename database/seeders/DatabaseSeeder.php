<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Entry;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['name' => 'School', 'color_theme' => 'emerald'],
            ['name' => 'Work', 'color_theme' => 'blue'],
            ['name' => 'Personal', 'color_theme' => 'pink'],
            ['name' => 'Health', 'color_theme' => 'amber'],
            ['name' => 'Ideas', 'color_theme' => 'indigo'],
        ])->mapWithKeys(function (array $category) {
            $model = Category::firstOrCreate(
                ['name' => $category['name']],
                ['color_theme' => $category['color_theme']]
            );

            return [$category['name'] => $model];
        });

        $entries = [
            [
                'title' => 'First Day of Vibecoding',
                'content' => 'Laravel felt overwhelming at first, but the MVC structure is starting to click. I successfully set up controllers and views today.',
                'mood' => 'Focused',
                'location' => 'Computer Lab',
                'is_favorite' => true,
                'category' => 'School',
            ],
            [
                'title' => 'We Fixed the PostgreSQL Error',
                'content' => 'The app finally connected after checking the PHP database extension and environment variables. Small setup wins feel huge.',
                'mood' => 'Happy',
                'location' => 'My Desk',
                'is_favorite' => true,
                'category' => 'Work',
            ],
            [
                'title' => 'Weekend Vibes',
                'content' => 'I got time to relax, watch movies, and disconnect for a bit. The reset helped me feel ready for the next school week.',
                'mood' => 'Happy',
                'location' => 'Home',
                'is_favorite' => false,
                'category' => 'Personal',
            ],
            [
                'title' => 'Lab 3 Planning Session',
                'content' => 'Outlined the chatbot requirements, wrote possible user questions, and listed the CRUD commands the AI assistant should handle.',
                'mood' => 'Focused',
                'location' => 'Library',
                'is_favorite' => true,
                'category' => 'School',
            ],
            [
                'title' => 'Debugging the Chat Widget',
                'content' => 'The floating widget needed clearer loading states and better error messages. It now feels easier to test while viewing entries.',
                'mood' => 'Focused',
                'location' => 'Study Table',
                'is_favorite' => false,
                'category' => 'Work',
            ],
            [
                'title' => 'Morning Walk',
                'content' => 'Took a quick walk before coding. It helped clear my head and made the rest of the day feel less rushed.',
                'mood' => 'Calm',
                'location' => 'Neighborhood',
                'is_favorite' => false,
                'category' => 'Health',
            ],
            [
                'title' => 'Feature Idea: Mood Insights',
                'content' => 'Maybe the journal dashboard can show mood trends by week and suggest gentle reminders when stressed entries pile up.',
                'mood' => 'Excited',
                'location' => 'Cafe',
                'is_favorite' => true,
                'category' => 'Ideas',
            ],
            [
                'title' => 'Group Check-in',
                'content' => 'We reviewed what was finished and split the remaining tasks. The main risk is making sure the demo is clear.',
                'mood' => 'Focused',
                'location' => 'Online Call',
                'is_favorite' => false,
                'category' => 'School',
            ],
            [
                'title' => 'Long Commute Reflection',
                'content' => 'Traffic was tiring, but I used the time to think through how natural language commands should map to journal actions.',
                'mood' => 'Tired',
                'location' => 'Bus',
                'is_favorite' => false,
                'category' => 'Personal',
            ],
            [
                'title' => 'Finished Entry Filters',
                'content' => 'Search, category filters, and mood filters are working together. The UI is much more useful for finding old thoughts.',
                'mood' => 'Happy',
                'location' => 'Computer Lab',
                'is_favorite' => true,
                'category' => 'Work',
            ],
            [
                'title' => 'Stressed About Deadlines',
                'content' => 'There are several requirements due close together. I need to prioritize the demo path and avoid unnecessary redesigns.',
                'mood' => 'Stressed',
                'location' => 'Bedroom',
                'is_favorite' => false,
                'category' => 'School',
            ],
            [
                'title' => 'Healthy Lunch Reminder',
                'content' => 'Packed lunch today instead of buying fast food. Small routine choices are easier when prepared the night before.',
                'mood' => 'Grateful',
                'location' => 'Campus',
                'is_favorite' => false,
                'category' => 'Health',
            ],
            [
                'title' => 'Presentation Notes',
                'content' => 'For the defense, explain API key safety, backend proxy calls, session history, and why destructive actions ask for confirmation.',
                'mood' => 'Focused',
                'location' => 'Library',
                'is_favorite' => true,
                'category' => 'School',
            ],
            [
                'title' => 'UI Polish Pass',
                'content' => 'Adjusted spacing and kept the chat widget compact so it does not block the journal list while users ask questions.',
                'mood' => 'Calm',
                'location' => 'Home Desk',
                'is_favorite' => false,
                'category' => 'Work',
            ],
            [
                'title' => 'Future Voice Notes',
                'content' => 'A nice future feature would be recording voice notes and asking the AI to summarize them into a clean journal entry.',
                'mood' => 'Excited',
                'location' => 'Cafe',
                'is_favorite' => false,
                'category' => 'Ideas',
            ],
        ];

        foreach ($entries as $entry) {
            Entry::firstOrCreate(
                ['title' => $entry['title']],
                [
                    'content' => $entry['content'],
                    'mood' => $entry['mood'],
                    'location' => $entry['location'],
                    'is_favorite' => $entry['is_favorite'],
                    'category_id' => $categories[$entry['category']]->id,
                ]
            );
        }
    }
}
