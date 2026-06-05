/**
 * Zebra GC420 label printing via Browser Print (localhost:9100).
 * Label sizes: 40×20 mm (320×160 dots) and 50×25 mm (400×200 dots) at 203 dpi.
 */
(function (global) {
    function printLabelModal() {
        return {
            open: false,
            selectedBarcode: '',
            labelSize: '40x20',
            qty: 1,
            printing: false,
            statusMsg: '',
            statusSuccess: false,

            generateZPL() {
                const code = this.selectedBarcode;
                if (this.labelSize === '40x20') {
                    return '^XA\n^CI28\n^PW320\n^LL160\n^LH0,0\n^FO10,10^A0N,28,28^FD' + code + '^FS\n^FO10,50^BQN,2,3^FDMA,' + code + '^FS\n^PQ' + this.qty + '\n^XZ';
                }

                return '^XA\n^CI28\n^PW400\n^LL200\n^LH0,0\n^FO10,10^A0N,32,32^FD' + code + '^FS\n^FO10,55^BQN,2,4^FDMA,' + code + '^FS\n^PQ' + this.qty + '\n^XZ';
            },

            async printLabel() {
                if (!this.selectedBarcode) {
                    return;
                }

                this.printing = true;
                this.statusMsg = 'Mencari printer...';
                this.statusSuccess = false;

                try {
                    console.log('[ZPL] Checking Browser Print...');
                    const availRes = await fetch('http://127.0.0.1:9100/available');
                    if (!availRes.ok) {
                        throw new Error('Browser Print tidak merespon (status ' + availRes.status + ')');
                    }

                    const availText = await availRes.text();
                    console.log('[ZPL] Available response:', availText);

                    const zpl = this.generateZPL();
                    console.log('[ZPL] Generated:', zpl);

                    this.statusMsg = 'Mengirim ke printer...';

                    let sent = false;

                    try {
                        const r = await fetch('http://127.0.0.1:9100/write', {
                            method: 'POST',
                            headers: { 'Content-Type': 'text/plain' },
                            body: zpl,
                        });
                        if (r.ok) {
                            sent = true;
                            console.log('[ZPL] Plain text write OK');
                        } else {
                            console.log('[ZPL] Plain text write failed:', r.status);
                        }
                    } catch (e) {
                        console.log('[ZPL] Plain text write error:', e.message);
                    }

                    if (!sent) {
                        try {
                            let printer = null;
                            try {
                                printer = JSON.parse(availText);
                                if (Array.isArray(printer)) {
                                    printer = printer[0];
                                }
                            } catch (e) {
                                printer = availText.trim();
                            }
                            const r = await fetch('http://127.0.0.1:9100/write', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ device: printer, data: zpl }),
                            });
                            if (r.ok) {
                                sent = true;
                                console.log('[ZPL] JSON write OK');
                            } else {
                                console.log('[ZPL] JSON write failed:', r.status);
                            }
                        } catch (e) {
                            console.log('[ZPL] JSON write error:', e.message);
                        }
                    }

                    if (!sent) {
                        throw new Error('Gagal mengirim ke printer. Cek console F12.');
                    }

                    this.statusMsg = 'Berhasil! ' + this.qty + ' label dikirim ke printer.';
                    this.statusSuccess = true;
                } catch (e) {
                    this.statusMsg = e.message;
                    this.statusSuccess = false;
                    console.error('[ZPL] Error:', e);
                } finally {
                    this.printing = false;
                }
            },
        };
    }

    global.printLabelModal = printLabelModal;
})(window);
