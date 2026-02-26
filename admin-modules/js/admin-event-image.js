console.log('JS geladen')
jQuery(document).ready(function($){
    // Achtergronden uploaden
    $('#upload_bg').on('click', function(e){
        e.preventDefault();
        const frame = wp.media({title:'Selecteer achtergronden', multiple:true, library: { type: 'image'}});
        frame.on('select', function(){
            const sel = frame.state().get('selection').map(a=>a.toJSON());
            let current = $('#background_ids').val().split(',').filter(Boolean);
            sel.forEach(a=>{
                if(!current.includes(a.id.toString())){
                    current.push(a.id);
                    $('#bg_preview').append(`
                        <div class="bg-item" data-id="${a.id}" style="position:relative; display:inline-block;">
                            <img src="${a.url}" style="max-width:150px; border:1px solid #ccc; padding:2px;">
                            <button type="button" class="remove-bg">X</button>
                        </div>
                    `);
                }
            });
            $('#background_ids').val(current.join(','));
        });
        frame.open();
    });

    // Achtergrond verwijderen
    $(document).on('click','.remove-bg', function(){
        const $item = $(this).closest('.bg-item');
        let ids = $('#background_ids').val().split(',').filter(Boolean);
        ids = ids.filter(v=>v!=$item.data('id').toString());
        $('#background_ids').val(ids.join(','));
        $item.remove();
    });

    // Overlay media selector
    $(document).on('click','.select-overlay', function(e){
        e.preventDefault();
        const $row = $(this).closest('tr');
        const $input = $row.find('input.overlay-id');
        const $preview = $row.find('.overlay-preview-cell');

        const frame = wp.media({title:'Selecteer overlay', multiple:false, library:{type:'image/png'}});
        frame.on('select', function(){
            const att = frame.state().get('selection').first().toJSON();
            $input.val(att.id);
            $preview.html(`<img src="${att.url}" style="max-width:120px; border:1px solid #ccc;">`);
        });
        frame.open();
    });

    // Overlay toevoegen
    let rowIndex = $('#overlay_table tbody tr.overlay-row-main').length;
    $('#add_overlay_row').on('click', function(e){
    e.preventDefault();

    const i = rowIndex++;

    const mainRow = `
    <tr class="overlay-row-main">
        <td class="overlay-preview-cell"></td>
        <td>
            <input type="number" name="overlays[${i}][id]" class="overlay-id" style="width:80px;">
            <button type="button" class="button select-overlay">Kies PNG</button>
        </td>
        <td>
            X:<input type="number" name="overlays[${i}][logo1_x]" style="width:50px;">
            Y:<input type="number" name="overlays[${i}][logo1_y]" style="width:50px;">
            W:<input type="number" name="overlays[${i}][logo_w]" style="width:50px;">
            H:<input type="number" name="overlays[${i}][logo_h]" style="width:50px;">
        </td>
        <td>
            X:<input type="number" name="overlays[${i}][logo2_x]" style="width:50px;">
            Y:<input type="number" name="overlays[${i}][logo2_y]" style="width:50px;">
        </td>
        <td>
            <button type="button" class="button button-link-delete remove-overlay">Verwijderen</button>
        </td>
    </tr>
    `;

    const textRow = `
    <tr class="overlay-row-text">
        <td colspan="5" style="background:#f9f9f9;">
            <strong>Teksten</strong>
            <div style="display:flex; gap:20px; margin-top:10px; flex-wrap:wrap;">
                ${['title','date','hour','location'].map(txt => `
                    <div style="border:1px solid #ddd; padding:10px; min-width:200px;">
                        <strong>${txt.toUpperCase()}</strong><br>
                        X:<input type="number" name="overlays[${i}][${txt}_x]" style="width:50px;">
                        Y:<input type="number" name="overlays[${i}][${txt}_y]" style="width:50px;"><br><br>
                        Font:
                        <select name="overlays[${i}][${txt}_font]">
                            <option value="Poppins-Black">Black</option>
                            <option value="Poppins-Bold">Bold</option>
                            <option value="Poppins-Regular">Regular</option>
                        </select>
                        <br><br>
                        Grootte:
                        <input type="number" name="overlays[${i}][${txt}_size]" style="width:60px;">
                    </div>
                `).join('')}
            </div>
        </td>
    </tr>
    `;

    $('#overlay_table tbody').append(mainRow + textRow);
});

    // Overlay verwijderen
    $(document).on('click','.remove-overlay', function(e){
    e.preventDefault();
    const $main = $(this).closest('tr');
    const $text = $main.next('.overlay-row-text');
    $main.remove();
    $text.remove();
});
    // Live preview
    $('#preview_image').on('click', function(e){
        e.preventDefault();
        const formData = $('form').serialize();
        $('#preview_img').hide();
        $.post(ajaxurl, formData+'&action=event_image_preview', function(res){
            if(res.data && res.data.img){
                $('#preview_img').attr('src','data:image/png;base64,'+res.data.img).show();
            } else {
                alert('Preview kon niet worden geladen.');
            }
        });
    });
});

