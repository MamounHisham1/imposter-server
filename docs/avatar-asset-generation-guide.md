# Avatar Asset Generation Guide

## Canvas Spec

| Property | Value |
|----------|-------|
| Canvas size | 512x512 px |
| Face center | 256, 280 (slightly below center for forehead room) |
| Face width | ~180px |
| Face height | ~220px |
| Background | Transparent PNG |
| Format | PNG with alpha channel |
| File naming | `{category}_{number}.png` (e.g., `hair_01.png`) |

## Alignment Rules

The face center is fixed at (256, 280). All layers align to this anchor:
- **Mask/Face**: Base layer. Top of head at ~y:170, chin at ~y:390.
- **Eyes**: ~y:260 to y:310, centered horizontally.
- **Hair**: Anchored to top of the mask layer.

Stack order: mask (bottom), eyes (middle), hair (top).

---

## Art Style: Flat 2D Cartoon

**Vibe:** Clean flat cartoon illustration. Simple geometric shapes, solid color fills, thick bold outlines, minimal shading. Think mobile avatar creators, character select screens, sticker art. Expressive and fun, not realistic.

**Key rules for AI generation:**
- Solid flat colors, NO gradients, NO textures, NO crosshatching
- Thick bold black outlines (3-4px stroke weight)
- Minimal or zero shading. If any, a single darker shade on one side only
- Simple geometric construction. Circles, ovals, rectangles
- Cartoon proportions: slightly exaggerated, expressive
- Transparent background with NO shadow, NO glow, NO backdrop

**Color palette:** Bright but not neon. Warm cartoon tones. Think of the palette in the reference image: warm skin tones, solid brown/black hair colors, clean distinct hues for variety.

---

## Prompt Templates

### Core prompt prefix (use for EVERY generation)

```
Flat 2D cartoon illustration, simple geometric shapes, solid flat color fills, thick bold black outlines, no shading, no gradients, no textures, no crosshatching, no shadows, transparent background, centered on canvas, clean vector-like edges, avatar character design, mobile game style
```

### Anti-realism suffix (add to prompts that keep turning out realistic)

```
NOT photorealistic, NOT 3D rendered, NOT semi-realistic, NO realistic skin texture, NO photographic elements, flat vector cartoon only
```

---

## Mask/Face Layer (generate 20)

Prompt template:
```
[core prefix], FACE SHAPE ONLY, no eyes no eyebrows no hair no mouth no nose, just the face outline and jawline shape with skin color fill, [VARIANT], front-facing, 512x512 transparent canvas
```

**Variants (mix these descriptors):**

**Face shapes:**
1. Round circle face, chubby cheeks, soft jawline
2. Oval elongated face, narrow chin, pointed jawline
3. Wide square face, strong angular jaw, broad forehead
4. Heart-shaped face, wide forehead tapering to small chin
5. Diamond face, narrow forehead and chin, wide cheekbones
6. Rectangular long face, straight strong jawline
7. Triangular face, narrow forehead, wide jaw
8. Pear-shaped face, narrow top, wide rounded bottom jaw

**Combine with characteristics:**
- Stubble shadow (darker flat area on jaw)
- Double chin / round chin
- Cleft chin (small V indent)
- Dimples (two small curved lines on cheeks)
- High cheekbones (angular cheek area)
- Freckles (scattered small dots on cheeks)
- Wrinkle lines (a few curved lines on forehead)
- Scar (single line across cheek)

**Skin tone variety:**
- Light peach, warm tan, golden brown, deep brown, dark brown, olive, rosy pink

Generate ~20 combining different shapes + characteristics + skin tones.

---

## Eyes Layer (generate 20)

Prompt template:
```
[core prefix], EYES AND EYEBROWS ONLY, no face no skin no nose no mouth, just the eyes and eyebrows floating on transparent canvas, [VARIANT], front-facing, 512x512 canvas, eyes positioned at center area
```

**Variants:**

**Eye shapes:**
1. Big round eyes, large circular irises, innocent and wide
2. Narrow squinting eyes, thin slits, suspicious and tough
3. Almond-shaped eyes, medium size, neutral expression
4. Half-closed droopy eyes, heavy eyelids, tired and bored
5. Cat-like angled eyes, corners pointing up, sharp and sly
6. Big sparkly eyes with star-like highlights, energetic and excited
7. Small beady dot eyes, minimal detail, stoic and unreadable
8. One eye bigger than the other, asymmetric, mischievous
9. Wide shocked eyes, tiny pupils, startled expression
10. Angry eyes with V-shaped angled eyebrows, intense

**Eyebrow styles:**
- Thick straight rectangles (default/bold)
- Thin arched curves (surprised/elegant)
- Bushy scribbled lines (rugged/wild)
- V-shaped angled down (angry/intense)
- Raised high arcs (surprised/innocent)
- Mono-brow connected line (comedic)
- No eyebrows (alien/mysterious)

**Eye colors:**
- Solid black dots, dark brown, blue, green, amber, hazel, red, heterochromia (one of each)

Generate ~20 combining different shapes + eyebrow styles + colors.

---

## Hair Layer (generate 20)

Prompt template:
```
[core prefix], HAIR ONLY, no face no eyes no skin, just the hair on top of head area, [VARIANT], front-facing portrait, 512x512 transparent canvas, hair positioned in upper portion to align with face below
```

**Variants:**

**Short styles:**
1. Buzz cut, tiny flat spikes all over the top
2. Crew cut, short neat hair on top, slightly longer in front
3. Spiky messy hair, multiple pointed spikes going up in different directions
4. Slicked back smooth, all hair pushed back flat
5. Side-parted neat, clean part on one side, hair combed over
6. Flat top, hair standing straight up in a flat block on top

**Medium styles:**
7. Messy shaggy hair, uneven wavy strands falling around
8. Curly mop, rounded mass of circular curls on top
9. Bowl cut, straight even hair going around the head like a bowl
10. Mohawk, strip of hair standing up in the middle, shaved sides
11. Wavy shoulder-length, flowing waves going past the ears
12. Afro, large rounded puffball of hair

**Long styles:**
13. Long straight hair falling past shoulders on both sides
14. Thick braids, two rope-like braids hanging down
15. Wild mane, big voluminous hair going everywhere
16. Ponytail visible from front, hair pulled back with a tuft on top

**Headwear (counts as hair slot):**
17. Cowboy hat, classic Western broad brim
18. Bandana tied around head, knot on the side
19. Top hat, tall formal cylinder
20. Beanie, round knit cap pulled down

**Hair colors:**
- Black, dark brown, brown, auburn, red, orange, blonde, platinum, white, gray, blue, green, pink

Generate ~20 mixing styles and colors.

---

## Generation Tips

**Getting the right style (most important):**
- If your AI tool keeps making realistic images, add the anti-realism suffix to EVERY prompt
- Start your prompt with "flat 2D cartoon vector illustration" as the very first words
- Use negative prompts if your tool supports them: `--no realistic, photographic, 3d, shading, gradient, texture, crosshatch, shadow`
- Generate in batches of 4 and iterate on the prompt until the style is locked, then do the full batch

**Tool-specific tips:**

**DALL-E 3 / ChatGPT:**
- Put "flat 2D cartoon vector illustration" as the FIRST words
- Add "in the style of a mobile avatar creator app" as a style anchor
- If it still looks 3D, add "drawn with simple shapes, like a children's book illustration"
- DALL-E can't do true transparent backgrounds. Plan to remove backgrounds in post

**Midjourney:**
- Add `--no realistic, photorealistic, 3d, photograph` to every prompt
- Use `--stylize 100` (lower = simpler, closer to prompt)
- Add `--s 100` to reduce artistic embellishment
- Consider `--niji 6` for anime/cartoon mode
- Use `--ar 1:1` for square

**Stable Diffusion:**
- Use a flat illustration / vector LoRA
- Set CFG scale higher (12-15) for closer prompt adherence
- Use "flat color, simple, vector, cartoon" in positive prompt
- Use "realistic, photorealistic, 3d, photograph, gradient, shading" in negative prompt

**Universal:**
- Generate 30-40 per category, keep the best 20
- Accept ~50% reject rate. Wrong style, bad alignment, too detailed = reject
- Sort rejects by "almost right" vs "totally wrong". The "almost" ones tell you how to tweak the prompt
- Once you get ONE image in perfect style, use it as an image prompt / style reference for all future generations

---

## Post-Processing Checklist

For each generated asset:
1. Remove background to transparent (use remove.bg, Photoshop, or GIMP)
2. Resize/crop to exactly 512x512px
3. Verify alignment: stack one mask + one eye + one hair, check they line up
4. Clean up any anti-aliased fuzzy edges (replace with clean hard edges for the cartoon style)
5. Save as PNG with alpha channel
6. Name with convention: `mask_01.png`, `eyes_15.png`, `hair_08.png`

---

## Test Stacking

Once you have 3-5 of each layer:
1. Open any image editor (GIMP, Photoshop, Figma)
2. Create a 512x512 canvas
3. Place a mask layer at the bottom
4. Add an eyes layer on top
5. Add a hair layer on top
6. Check: do the eyes sit naturally in the face? Does the hair sit on top of the head?
7. Adjust individual layer positioning if needed

If layers don't align, the fix is shifting the layer content within the 512x512 canvas (move the face up, or the eyes down, etc.), NOT changing the canvas size.
