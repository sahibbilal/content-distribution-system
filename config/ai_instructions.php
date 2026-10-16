<?php

// Starter text of the editable AI rulebook (Admin > AI assistant). Whatever a user saves replaces these;
// "Reset to default" brings them back. `used_by` says where a section is already applied.
return [

    'sections' => [

        'general' => [
            'title' => 'How to create a post',
            'help' => 'The steps every post follows. Used for every Ask AI request.',
            'used_by' => 'Every post',
            'body' => <<<'TXT'
Follow these steps for every request:
1. Understand the request: the topic, the audience, the channels and whether an image is wanted.
2. Research only when the request asks for it or the topic is recent. Use only what the sources say. Never invent facts, numbers, quotes or names.
3. Choose ONE clear idea per post. One post, one idea.
4. Open with a hook in the first line: a question, a surprising fact or a bold claim that is true.
5. Give value in the lines that follow: what happened, why it matters, what the reader can do.
6. End with a light call to action (a question, "follow for more") when it fits. Do not beg for likes.
7. Write in the language of the request. If the request is in Urdu, write Urdu; keep English technical terms in English letters.
8. Keep the tone friendly and confident, like a developer explaining to a colleague. At most two emojis.
9. Do not repeat anything listed under "Already written" in this project.
TXT,
        ],

        'format' => [
            'title' => 'Format for each channel',
            'help' => 'Length and layout per channel, and how hashtags are used.',
            'used_by' => 'Every post',
            'body' => <<<'TXT'
X: one standalone idea, written separately from the long text (not a cut-off copy). The text and its hashtags together must fit the character limit, spaces included. No threads. At most 3 hashtags.
LinkedIn: 600 to 1,300 characters. Short paragraphs of 1 to 2 lines with blank lines between them. A hook as the first line. No hashtags inside the text.
Facebook: 2 to 5 short lines, conversational. A question at the end works well.
Instagram: a caption of 1 to 3 short paragraphs that talks about the picture. The hashtags go in the hashtags list, not in the text.
Website: the first line is the title. Then the body in short paragraphs.
Hashtags: 3 to 8, relevant, a mix of broad (#AI) and specific (#Laravel12). Written without the # sign in the list. No spammy or unrelated tags.
TXT,
        ],

        'remember' => [
            'title' => 'Always remember',
            'help' => 'Who you are, who reads your posts, your voice, and the rules that never change. Edit this first.',
            'used_by' => 'Every post',
            'body' => <<<'TXT'
About me: (write who you are and what your page is about, e.g. "Bilal Mahmood, a full-stack developer from Pakistan who shares practical tech and AI tips").
My audience: (e.g. developers and tech learners, mostly in Pakistan, beginner to intermediate).
My voice: simple words, short sentences, practical, a little humour. No jargon without a one-line explanation.

Never:
- state a claim you cannot support with a source or with the PDF text;
- write about politics, religion or anything that attacks a person or group;
- include personal data, private links or secrets;
- promise income or guaranteed results;
- copy another writer's text. Say it in new words and name the source when you use one.
TXT,
        ],

        'images' => [
            'title' => 'Images',
            'help' => 'How made pictures should look, and what to check in pictures you upload.',
            'used_by' => 'Posts with an image',
            'body' => <<<'TXT'
Made images: square (1:1), clean and modern, one clear subject, strong contrast, plenty of empty space. No text, logos, watermarks or real people's faces in the picture. Colours: deep blue and white with one warm accent.
Uploaded images: look at every image. Say it is not ok when it is blurry, too dark, cropped badly, shows private information, or does not match the request. Write the post about what the image really shows.
TXT,
        ],

        'script' => [
            'title' => 'Scripts',
            'help' => 'How a spoken script is written. Used by the video script project (next step).',
            'used_by' => 'Video scripts (coming next)',
            'body' => <<<'TXT'
Conversational Urdu, short sentences, no heavy Persian or Arabic words. English technical terms stay in English letters. Numbers are written in words because they are spoken.
Structure: a hook in the first 10 seconds, what happened, why it matters, what it means for developers in Pakistan, then one closing line asking the viewer to follow.
Length: about 3 to 4 minutes spoken (roughly 500 to 650 words).
Mark 2 or 3 strong 30 to 55 second parts that stand alone as Shorts with [SHORT1]...[/SHORT1].
Use only facts from the sources. A fact found in only one source is never used as a headline claim.
TXT,
        ],

        'video' => [
            'title' => 'Videos',
            'help' => 'Titles, descriptions, Shorts and thumbnails. Used by the video script project (next step).',
            'used_by' => 'Video scripts (coming next)',
            'body' => <<<'TXT'
Long video: 3 to 4 minutes, 16:9, captions burned in. Shorts: 2 to 3, each under 60 seconds, 9:16.
Titles: under 70 characters, clear, no clickbait lies. Urdu with one English keyword is fine.
Descriptions: 2 to 3 lines, then 3 to 5 hashtags.
Thumbnail: a headline of 3 to 5 words.
Every video needs a source list in the description when it reports news.
TXT,
        ],

        'pdf' => [
            'title' => 'Reading PDFs',
            'help' => 'How saved PDFs are turned into posts. Used by PDF posts (next step).',
            'used_by' => 'PDF posts (coming next)',
            'body' => <<<'TXT'
Use only the text of the PDF. Do not add outside facts.
Split the PDF into sections and write one post per section. Do not use the same section twice unless asked.
Name the page or section a fact comes from in your notes. Quote at most one short sentence; otherwise write in new words.
Explain numbers and terms carefully and exactly as the PDF states them.
If a section is unclear, empty or only a list of references, skip it and say so.
TXT,
        ],
    ],

    // Projects created for a new user. Options are the defaults chosen in Ask AI when the project is picked.
    'projects' => [
        [
            'name' => 'Latest trends',
            'type' => 'trends',
            'options' => ['web_search' => true, 'hashtags' => true, 'make_image' => false],
            'instructions' => <<<'TXT'
Write a post about what is trending right now in the topic of the request (default topic: AI and software development).
Search the web first and use only what the sources say. Name the source in the post (for example "According to <site>").
Say why it matters for developers and what they can try today. Add an image only when the request asks for one.
TXT,
        ],
        [
            'name' => 'Jokes',
            'type' => 'jokes',
            'options' => ['web_search' => false, 'hashtags' => true, 'make_image' => false],
            'instructions' => <<<'TXT'
Write short, clean, original jokes for developers and tech people. Setup and punchline in 1 to 3 lines.
Vary the style: one-liner, dialogue, "programmer vs. computer", relatable work-life, Stack Overflow, bugs, deadlines, AI.
Nothing offensive, no jokes about people, religion or politics. Never reuse a joke from "Already written", and avoid its punchline too.
Hashtags: #programming #developerhumor plus one specific tag.
TXT,
        ],
    ],
];
