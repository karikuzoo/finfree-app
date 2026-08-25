<?php

namespace App\Services;

use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menyimpan foto profil sebagai gambar persegi berukuran tetap.
 *
 * Berkas yang diunggah TIDAK disimpan apa adanya. Alasannya:
 *
 * 1. Foto dari ponsel biasanya berukuran beberapa megabyte, sementara avatar
 *    ditampilkan pada 32–96 piksel. Menyajikan berkas aslinya berarti setiap
 *    pemuatan halaman menyeret data ratusan kali lebih besar dari yang perlu.
 * 2. Berkas yang diunggah pengguna tidak boleh dipercaya begitu saja. Dengan
 *    membaca ulang gambarnya lalu menulis ulang dari nol, seluruh metadata —
 *    termasuk lokasi GPS yang sering tertanam di foto ponsel — ikut hilang,
 *    dan berkas yang menyamar sebagai gambar akan gagal di tahap ini.
 *
 * Memakai GD bawaan PHP, tanpa paket tambahan.
 */
class AvatarService
{
    /** Sisi gambar hasil, dalam piksel. Cukup untuk tampilan retina. */
    private const SIZE = 256;

    private const QUALITY = 82;

    private const DISK = 'public';

    private const DIRECTORY = 'avatars';

    /**
     * Simpan foto baru dan hapus yang lama. Mengembalikan jalur relatifnya.
     */
    public function store(User $user, UploadedFile $file): string
    {
        $image = $this->readImage($file);
        $square = $this->cropToSquare($image);
        $resized = $this->resize($square);

        $path = self::DIRECTORY.'/'.Str::random(40).'.webp';

        ob_start();
        imagewebp($resized, null, self::QUALITY);
        $binary = ob_get_clean();

        imagedestroy($resized);

        Storage::disk(self::DISK)->put($path, $binary);

        $this->deleteFile($user->avatar_path);

        return $path;
    }

    /** Hapus foto pengguna beserta berkasnya. */
    public function remove(User $user): void
    {
        $this->deleteFile($user->avatar_path);

        $user->forceFill(['avatar_path' => null])->save();
    }

    /**
     * Hapus berkas tanpa menyentuh basis data — dipakai saat akun dihapus,
     * ketika barisnya memang akan lenyap (FR-37). Tanpa ini, berkas foto
     * tertinggal selamanya sebagai sampah yang tidak dimiliki siapa pun.
     */
    public function deleteFile(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    private function readImage(UploadedFile $file): GdImage
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($image === false) {
            // Validasi seharusnya sudah menyaring ini, tetapi berkas yang
            // ekstensinya benar sementara isinya bukan gambar tetap bisa lolos.
            throw new RuntimeException('Berkas tidak dapat dibaca sebagai gambar.');
        }

        return $this->applyExifOrientation($image, $file);
    }

    /**
     * Foto ponsel sering tersimpan dalam orientasi "salah" disertai penanda
     * EXIF yang memberi tahu cara memutarnya. GD mengabaikan penanda itu, jadi
     * tanpa langkah ini avatar bisa tampil miring atau terbalik.
     */
    private function applyExifOrientation(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = $exif['Orientation'] ?? null;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated instanceof GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    /** Potong bagian tengah agar rasionya 1:1 sebelum diperkecil. */
    private function cropToSquare(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);

        if ($width === $height) {
            return $image;
        }

        $cropped = imagecrop($image, [
            'x' => (int) (($width - $side) / 2),
            'y' => (int) (($height - $side) / 2),
            'width' => $side,
            'height' => $side,
        ]);

        if ($cropped === false) {
            return $image;
        }

        imagedestroy($image);

        return $cropped;
    }

    private function resize(GdImage $image): GdImage
    {
        $target = imagecreatetruecolor(self::SIZE, self::SIZE);

        // WebP mendukung transparansi; pertahankan agar PNG beralpha tidak
        // berubah jadi latar hitam.
        imagealphablending($target, false);
        imagesavealpha($target, true);

        imagecopyresampled(
            $target,
            $image,
            0, 0, 0, 0,
            self::SIZE,
            self::SIZE,
            imagesx($image),
            imagesy($image),
        );

        imagedestroy($image);

        return $target;
    }
}
