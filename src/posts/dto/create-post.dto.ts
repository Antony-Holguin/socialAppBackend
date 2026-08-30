import { IsString, MinLength, IsNumber } from 'class-validator';
export class CreatePostDto {
  @IsString()
  @MinLength(5)
  title!: string;

  @IsString()
  imagePath?: string;

  @IsString()
  description?: string;
  @IsNumber()
  userId!: number;
}
