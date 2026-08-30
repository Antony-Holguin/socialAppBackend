import { Injectable } from '@nestjs/common';
import { CreatePostDto } from './dto/create-post.dto';
import { UpdatePostDto } from './dto/update-post.dto';
import { PrismaService } from 'prisma/prisma.service';
import { Post } from './entities/post.entity';
import { JwtUser } from 'src/auth/types/jwt-payload.type';

@Injectable()
export class PostsService {
  //Inject prisma service
  constructor(private readonly postService: PrismaService) {}

  private readonly publicSelect = {
    id: true,
    title: true,
    imagePath: true,
    description: true,
    userId: true,
  } as const;

  async create(createPostDto: CreatePostDto, user: JwtUser): Promise<Post> {
    return await this.postService.post.create({
      data: {
        title: createPostDto.title,
        description: createPostDto.description,
        imagePath: createPostDto.imagePath,
        userId: user.id,
      },
      select: this.publicSelect,
    });
  }

  findAll() {
    return `This action returns all posts`;
  }

  findOne(id: number) {
    return `This action returns a #${id} post`;
  }

  update(id: number, updatePostDto: UpdatePostDto) {
    return `This action updates a #${id} post`;
  }

  remove(id: number) {
    return `This action removes a #${id} post`;
  }
}
