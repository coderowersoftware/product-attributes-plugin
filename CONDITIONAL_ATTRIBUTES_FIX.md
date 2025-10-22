# Conditional Attributes Reset Functionality

## Problem

When a product has conditional attributes (attributes that depend on previous selections), users could not "go back" to simpler combinations once they selected options that opened additional attributes.

### Example Scenario

**Product:** Door Handle with Lock Options
- **Attribute 1: Lock** with 2 options:
  - "Handle Pair Only" → no additional attributes needed (Door Thickness = "none")
  - "With 3 Pin Lock Body" → requires Door Thickness selection
  
- **Attribute 2: Door Thickness** with 1 option:
  - "Upto 39mm"

**Previous Behavior:**
1. User selects "With 3 Pin Lock Body" → Door Thickness appears
2. User selects "Upto 39mm" in Door Thickness
3. "Handle Pair Only" becomes **disabled** (grayed out)
4. User cannot go back to "Handle Pair Only" without refreshing the page

**Desired Behavior:**
1. User selects "With 3 Pin Lock Body" → Door Thickness appears
2. User selects "Upto 39mm" in Door Thickness
3. "Handle Pair Only" remains **clickable** with a subtle visual indicator
4. User can click "Handle Pair Only" → automatically deselects "With 3 Pin Lock Body" and "Door Thickness"

## Solution Implemented

### 1. **Detect "Reset Variants"** (`frontend.js` lines 247-288)

Added `checkIfResetVariant(ai, vi)` function that identifies variants which have `none` for all subsequent attributes. These are considered "reset variants" because they don't require any additional selections.

**Logic:**
- Finds all combinations that include the clicked variant
- Checks if ALL those combinations have `none` (or no value) for every subsequent attribute
- If yes, it's a reset variant

### 2. **Allow Clicking Reset Variants** (`frontend.js` lines 690-720)

Modified the button click handler to:
- Allow clicking **even if the button is disabled**, but only for reset variants
- When a reset variant is clicked:
  - Clear all subsequent attribute selections
  - Select the reset variant
  - Update the UI (which hides irrelevant attributes)

### 3. **Visual Styling for Reset Variants** (`frontend.js` lines 392-402, `frontend.css` lines 255-289)

- Added `ecv-reset-option` CSS class to reset variants
- Visual changes:
  - **Slightly faded** (opacity: 0.75) to show it's different from normal options
  - **Small reset icon** (↺) in top-right corner
  - **Full opacity on hover** to indicate it's clickable
  - **Cursor remains pointer** (not "not-allowed")

### 4. **Update Available Options Logic** (`frontend.js` lines 392-402)

Modified `updateAvailableOptions()` to:
- Check if each variant is a reset variant
- If yes, don't disable it (remove `disabled` class and add `ecv-reset-option` class)
- If no, apply normal enable/disable logic

## How It Works

### CSV Format

Your CSV defines combinations like this:

```csv
Lock,Door Thickness
Handle Pair Only,none         → This is a RESET variant (no dependencies)
With 3 Pin Lock Body,Upto 39mm → This requires Door Thickness
```

### User Flow

1. **Initial State:**
   - Lock: "Handle Pair Only" is pre-selected
   - Door Thickness: Hidden (because it's "none")

2. **User Selects Complex Option:**
   - User clicks "With 3 Pin Lock Body"
   - Door Thickness appears with "Upto 39mm"
   - "Handle Pair Only" stays visible with a reset icon ↺

3. **User Wants to Go Back:**
   - User clicks "Handle Pair Only" (even though it's faded)
   - System automatically:
     - Deselects "With 3 Pin Lock Body"
     - Hides Door Thickness
     - Selects "Handle Pair Only"
     - Updates price to the simpler combination

## Technical Details

### Key Functions

1. **`checkIfResetVariant(ai, vi)`** - Determines if a variant is a reset option
   - Parameters:
     - `ai` = attribute index
     - `vi` = variant index within that attribute
   - Returns: `true` if this variant has "none" for all subsequent attributes

2. **Button Click Handler** - Modified to handle reset variants
   - Removes `:not(.disabled)` selector to allow clicking all buttons
   - Checks if clicked variant is a reset variant
   - If yes: clears subsequent selections and selects this variant
   - If no: applies normal toggle behavior

3. **`updateAvailableOptions()`** - Modified to mark reset variants
   - Checks each variant to see if it's a reset variant
   - Adds `ecv-reset-option` class instead of `disabled` class

### CSS Classes

- `.ecv-reset-option` - Applied to reset variants
  - Visual: 75% opacity, reset icon (↺), pointer cursor
  - Hover: Full opacity, highlighted background
  
- `.disabled` - Applied to truly unavailable variants
  - Visual: 50% opacity, not-allowed cursor, no hover effect

## Testing

### Test Case 1: Simple to Complex
1. Load product page with conditional attributes
2. Default selection should be the simplest option (e.g., "Handle Pair Only")
3. Select a complex option (e.g., "With 3 Pin Lock Body")
4. Verify dependent attribute appears (Door Thickness)
5. Select dependent option (e.g., "Upto 39mm")
6. **Verify reset option stays clickable with reset icon**

### Test Case 2: Complex to Simple (Reset)
1. Start with complex combination selected
2. Click the reset option (e.g., "Handle Pair Only")
3. **Verify:**
   - Complex option is deselected
   - Dependent attributes are hidden
   - Reset option is now active
   - Price updates to the simpler combination

### Test Case 3: Multiple Attributes
1. Test with products that have 3+ attributes
2. Verify reset works at any level
3. Example: If Lock is reset, both Lock and all subsequent attributes should reset

## Browser Compatibility

- ✅ Chrome/Edge (Chromium)
- ✅ Firefox
- ✅ Safari
- ✅ Mobile browsers

## Notes

- Reset icon (↺) uses Unicode character U+21BA (anticlockwise open circle arrow)
- The feature is backward-compatible - products without conditional attributes work the same as before
- The reset functionality only applies to button-type attributes (not dropdowns or radio buttons in this implementation)

## Future Enhancements

If needed, could extend to:
1. Add reset functionality for dropdown and radio button attributes
2. Show a tooltip explaining the reset icon
3. Add animation when resetting (fade out dependent attributes)
4. Add a "Reset All" button that returns to the simplest combination
